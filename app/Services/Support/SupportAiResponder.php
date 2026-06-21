<?php

namespace App\Services\Support;

use App\Models\SupportConversation;
use App\Models\SupportMessage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class SupportAiResponder
{
    public function maybeReply(SupportConversation $conversation, SupportMessage $customerMessage): ?SupportMessage
    {
        if ($conversation->status === SupportConversation::STATUS_CLOSED) {
            return null;
        }

        $hasHumanAdminMessage = $conversation->messages()
            ->where('sender_type', SupportMessage::SENDER_ADMIN)
            ->exists();

        if ($hasHumanAdminMessage) {
            return null;
        }

        $body = $this->generate($conversation, $customerMessage);

        if ($body === null) {
            return null;
        }

        $message = $conversation->messages()->create([
            'sender_type' => SupportMessage::SENDER_AI,
            'sender_name' => 'KGM AI Müşteri Hizmetleri',
            'body' => $body,
            'metadata' => [
                'provider' => 'gemini',
                'model' => config('services.gemini.model'),
            ],
        ]);

        $conversation->touchLastMessage($message);

        return $message;
    }

    private function generate(SupportConversation $conversation, SupportMessage $customerMessage): ?string
    {
        $key = (string) config('services.gemini.key');

        if ($key === '') {
            return null;
        }

        $model = (string) (config('services.gemini.model') ?: 'gemini-2.5-flash');

        try {
            $history = $conversation->messages()
                ->latest('id')
                ->limit(8)
                ->get()
                ->reverse()
                ->map(fn (SupportMessage $message): array => [
                    'sender' => $message->sender_type,
                    'body' => $message->body,
                ])
                ->values()
                ->all();

            $response = Http::timeout(12)
                ->retry(1, 350)
                ->withQueryParameters(['key' => $key])
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'contents' => [[
                        'role' => 'user',
                        'parts' => [[
                            'text' => $this->prompt($conversation, $customerMessage, $history),
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.25,
                        'topP' => 0.8,
                        'maxOutputTokens' => 480,
                    ],
                ]);

            if (! $response->ok()) {
                Log::warning('Support Gemini non-ok', [
                    'status' => $response->status(),
                    'body' => Str::limit($response->body(), 400),
                ]);

                return null;
            }

            $text = trim((string) data_get($response->json(), 'candidates.0.content.parts.0.text', ''));

            return $text !== '' ? Str::limit($text, 900, '') : null;
        } catch (Throwable $e) {
            Log::warning('Support Gemini failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    private function prompt(SupportConversation $conversation, SupportMessage $customerMessage, array $history): string
    {
        $historyJson = json_encode($history, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $customerName = $conversation->customer_name ?: 'Müşteri';

        return <<<PROMPT
Sen Karacabey Gross Market müşteri hizmetleri asistanısın. Türkçe, kısa, nazik ve çözüm odaklı yanıt ver.

Kurallar:
- Sipariş, ödeme iadesi, kart, kişisel veri, kesin teslimat saati, fiyat garantisi veya stok garantisi gibi konularda kesin söz verme.
- Hassas veya hesap gerektiren durumda "ekibimize aktarıyorum" diyerek insan müşteri temsilcisine yönlendir.
- Market, ürün arama, konum, teslimat bölgesi, WhatsApp, kayıt/giriş ve sepet konusunda yardımcı ol.
- Yanıtı 2-4 cümleyle sınırla. Markdown tablo kullanma.

Müşteri adı: {$customerName}
Konu: {$conversation->subject}
Son müşteri mesajı: {$customerMessage->body}
Konuşma geçmişi: {$historyJson}
PROMPT;
    }
}
