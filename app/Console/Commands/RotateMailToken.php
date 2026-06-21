<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Mail-service admin token'ını mevcut admin kullanıcının password hash'inden türetir.
 *
 * Mantık:
 *  MAIL_ADMIN_TOKEN = hash_hmac('sha256', APP_KEY, admin.id ':' admin.password)
 *
 * Yani admin şifresi her değiştiğinde token da değişir. Token sızdırılırsa
 * şifre değiştirip bu komutu yeniden çalıştırmak yeterli.
 *
 *   php artisan mail:rotate-token             # ilk admin'i kullan
 *   php artisan mail:rotate-token --email=x   # belirli admin
 *   php artisan mail:rotate-token --apply     # .env'e yaz + container restart
 */
class RotateMailToken extends Command
{
    protected $signature = 'mail:rotate-token
        {--email= : Kullanılacak admin e-postası (verilmezse ilk admin)}
        {--apply : Env dosyasına yaz ve container restart talimatı ver}
        {--env-path= : Güncellenecek env dosyası. Varsayılan: production ortamında .env.production, diğer ortamlarda .env}';

    protected $description = 'Mail-service admin token\'ını admin password hash\'inden türetir';

    public function handle(): int
    {
        $email = $this->option('email');
        $query = User::query()->where('is_admin', true);
        if ($email) {
            $query->where('email', $email);
        }
        $user = $query->orderBy('id')->first();

        if (! $user) {
            $this->error('Admin kullanıcı bulunamadı.'.($email ? " (email: {$email})" : ''));
            return self::FAILURE;
        }

        $appKey = (string) config('app.key');
        if (! $appKey) {
            $this->error('APP_KEY tanımlı değil. `php artisan key:generate` çalıştırın.');
            return self::FAILURE;
        }
        // base64: ile başlıyorsa decode et
        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        $token = hash_hmac('sha256', $user->id.':'.$user->password, $appKey);

        $this->info('═══ Mail Admin Token ═══');
        $this->line('Admin:    '.$user->email.' (id='.$user->id.')');
        $this->line('Token:    '.$token);
        $this->newLine();

        if (! $this->option('apply')) {
            $this->comment('Önizleme. Uygulamak için: --apply');
            $this->newLine();
            $this->line('Manuel kullanım için:');
            $this->line('  .env dosyasında MAIL_ADMIN_TOKEN='.$token);
            $this->line('  Sonra: docker restart kgm-mail-service');
            return self::SUCCESS;
        }

        $envPath = $this->option('env-path')
            ? base_path((string) $this->option('env-path'))
            : base_path(app()->environment('production') ? '.env.production' : '.env');

        if (! file_exists($envPath)) {
            $this->error('.env bulunamadı: '.$envPath);
            return self::FAILURE;
        }

        $envContents = file_get_contents($envPath);
        $newLine = 'MAIL_ADMIN_TOKEN='.$token;
        if (preg_match('/^MAIL_ADMIN_TOKEN=.*$/m', $envContents)) {
            $envContents = preg_replace('/^MAIL_ADMIN_TOKEN=.*$/m', $newLine, $envContents);
        } else {
            $envContents = rtrim($envContents, "\n")."\n\n".$newLine."\n";
        }
        file_put_contents($envPath, $envContents);
        $this->info('✓ '.basename($envPath).' güncellendi.');

        $this->newLine();
        $this->warn('SONRAKİ ADIM: Mail container\'ını yeni env ile yeniden başlatın:');
        $this->line('  docker compose -f docker-compose.production.yml up -d kgm-mail-service');
        $this->line('  veya: docker restart kgm-mail-service (env değişikliği için yeterli değil — compose up gerekiyor)');

        return self::SUCCESS;
    }
}
