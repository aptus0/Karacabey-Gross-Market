<?php

namespace App\Support;

class HttpStatusCatalog
{
    /**
     * @return array<int, array{code:int, text:string, title:string, category:string, severity:string, message:string, recommendation:string}>
     */
    public static function all(): array
    {
        return [
            100 => self::item(100, 'Continue', 'İstek devam ediyor', '1xx Bilgilendirme', 'info', 'Sunucu isteğin ilk bölümünü aldı ve devam edebilirsiniz.', 'İstek otomatik olarak devam edecektir. İşlem takılırsa sayfayı yenileyin.'),
            101 => self::item(101, 'Switching Protocols', 'Protokol değiştiriliyor', '1xx Bilgilendirme', 'info', 'Sunucu bağlantı protokolünü değiştirmeyi kabul etti.', 'Bağlantı otomatik olarak yeni protokole geçer. Sorun sürerse sayfayı yenileyin.'),
            102 => self::item(102, 'Processing', 'İstek işleniyor', '1xx Bilgilendirme', 'info', 'İstek alındı ve arka planda işleniyor.', 'Biraz bekleyin. Aynı işlemi art arda tekrar göndermeyin.'),
            103 => self::item(103, 'Early Hints', 'Ön yükleme bilgisi gönderildi', '1xx Bilgilendirme', 'info', 'Tarayıcıya erken yükleme ipuçları gönderildi.', 'Bu teknik bir bilgilendirmedir. Kullanıcı aksiyonu gerekmez.'),

            200 => self::item(200, 'OK', 'İşlem başarılı', '2xx Başarı', 'success', 'İstek başarıyla tamamlandı.', 'Sayfayı kullanmaya devam edebilirsiniz.'),
            201 => self::item(201, 'Created', 'Kayıt oluşturuldu', '2xx Başarı', 'success', 'Yeni kayıt başarıyla oluşturuldu.', 'Oluşturulan kaydı panelden kontrol edebilirsiniz.'),
            202 => self::item(202, 'Accepted', 'İstek kabul edildi', '2xx Başarı', 'success', 'İstek kabul edildi ve işlenmek üzere sıraya alındı.', 'İşlem sonucunu bildirim veya durum ekranından takip edin.'),
            203 => self::item(203, 'Non-Authoritative Information', 'Kaynak dışı bilgi', '2xx Başarı', 'success', 'Yanıt başarıyla geldi ancak bilgi ara kaynak üzerinden üretildi.', 'Veri kritikse ana kaynaktan tekrar doğrulayın.'),
            204 => self::item(204, 'No Content', 'İçerik yok', '2xx Başarı', 'success', 'İşlem başarılı, ancak gösterilecek içerik yok.', 'Bir önceki ekrana dönebilir veya işleminize devam edebilirsiniz.'),
            205 => self::item(205, 'Reset Content', 'Form sıfırlanmalı', '2xx Başarı', 'success', 'İşlem başarılı ve formun temizlenmesi gerekiyor.', 'Form alanlarını temizleyip yeni işlem başlatabilirsiniz.'),
            206 => self::item(206, 'Partial Content', 'Kısmi içerik', '2xx Başarı', 'success', 'İçeriğin sadece istenen bölümü gönderildi.', 'Dosya veya medya yüklenmesi devam ediyorsa bekleyin.'),

            300 => self::item(300, 'Multiple Choices', 'Birden fazla seçenek', '3xx Yönlendirme', 'redirect', 'İstek için birden fazla uygun hedef bulundu.', 'Doğru hedefi seçin veya ana sayfadan tekrar ilerleyin.'),
            301 => self::item(301, 'Moved Permanently', 'Kalıcı yönlendirme', '3xx Yönlendirme', 'redirect', 'Sayfa kalıcı olarak başka bir adrese taşındı.', 'Yeni adrese yönlendirileceksiniz. Eski bağlantıyı güncelleyin.'),
            302 => self::item(302, 'Found', 'Geçici yönlendirme', '3xx Yönlendirme', 'redirect', 'Sayfa geçici olarak başka bir adrese yönlendiriliyor.', 'Yönlendirme tamamlanmazsa sayfayı yenileyin.'),
            303 => self::item(303, 'See Other', 'Başka adrese bakın', '3xx Yönlendirme', 'redirect', 'İşlem sonucu farklı bir adreste görüntülenmeli.', 'Yönlendirilen sayfadan devam edin.'),
            304 => self::item(304, 'Not Modified', 'İçerik değişmedi', '3xx Yönlendirme', 'redirect', 'İçerik son ziyaretinizden beri değişmedi.', 'Tarayıcı önbelleğindeki içerik kullanılabilir.'),
            307 => self::item(307, 'Temporary Redirect', 'Geçici yönlendirme', '3xx Yönlendirme', 'redirect', 'İstek geçici olarak farklı adrese yönlendirildi.', 'İşlem metodunuz korunarak yönlendirme yapılır.'),
            308 => self::item(308, 'Permanent Redirect', 'Kalıcı yönlendirme', '3xx Yönlendirme', 'redirect', 'Kaynak kalıcı olarak yeni adrese taşındı.', 'Yeni adresi kullanmaya devam edin.'),

            400 => self::item(400, 'Bad Request', 'Geçersiz istek', '4xx İstemci Hatası', 'warning', 'Gönderilen istek sistem tarafından okunamadı veya eksik.', 'Form alanlarını ve bağlantıyı kontrol edip tekrar deneyin.'),
            401 => self::item(401, 'Unauthorized', 'Oturum gerekli', '4xx İstemci Hatası', 'warning', 'Bu işlem için giriş yapmanız gerekiyor.', 'Hesabınıza giriş yapıp işlemi yeniden deneyin.'),
            402 => self::item(402, 'Payment Required', 'Ödeme gerekli', '4xx İstemci Hatası', 'warning', 'Bu işlem için ödeme veya ödeme onayı gerekiyor.', 'Sepetinizi ve ödeme durumunuzu kontrol edin.'),
            403 => self::item(403, 'Forbidden', 'Erişim reddedildi', '4xx İstemci Hatası', 'danger', 'Bu kaynağa erişim yetkiniz yok.', 'Yetki gerekiyorsa yöneticiyle iletişime geçin.'),
            404 => self::item(404, 'Not Found', 'Sayfa bulunamadı', '4xx İstemci Hatası', 'warning', 'Aradığınız sayfa veya kayıt bulunamadı.', 'Bağlantıyı kontrol edin veya ana sayfadan tekrar arayın.'),
            405 => self::item(405, 'Method Not Allowed', 'Metot desteklenmiyor', '4xx İstemci Hatası', 'warning', 'Bu adres için kullanılan HTTP metodu geçerli değil.', 'Sayfayı yenileyin veya işlemi doğru ekrandan başlatın.'),
            406 => self::item(406, 'Not Acceptable', 'Yanıt formatı uygun değil', '4xx İstemci Hatası', 'warning', 'İstenen yanıt formatı bu kaynak tarafından desteklenmiyor.', 'Tarayıcı veya API istemci ayarlarınızı kontrol edin.'),
            407 => self::item(407, 'Proxy Authentication Required', 'Proxy kimliği gerekli', '4xx İstemci Hatası', 'warning', 'Ağ geçidi veya proxy kimlik doğrulaması istiyor.', 'Ağ ayarlarınızı veya proxy hesabınızı kontrol edin.'),
            408 => self::item(408, 'Request Timeout', 'İstek zaman aşımına uğradı', '4xx İstemci Hatası', 'warning', 'İstek beklenen sürede tamamlanamadı.', 'Bağlantınızı kontrol edip tekrar deneyin.'),
            409 => self::item(409, 'Conflict', 'Çakışma oluştu', '4xx İstemci Hatası', 'warning', 'İşlem mevcut kayıt veya durumla çakışıyor.', 'Sayfayı yenileyip güncel veriyle tekrar deneyin.'),
            410 => self::item(410, 'Gone', 'İçerik kaldırıldı', '4xx İstemci Hatası', 'warning', 'Bu kaynak artık yayında değil.', 'Benzer ürün veya içerikleri arama alanından bulabilirsiniz.'),
            411 => self::item(411, 'Length Required', 'İçerik uzunluğu gerekli', '4xx İstemci Hatası', 'warning', 'İstek gövdesi için uzunluk bilgisi gerekli.', 'İstemcinizin Content-Length gönderdiğinden emin olun.'),
            412 => self::item(412, 'Precondition Failed', 'Ön koşul başarısız', '4xx İstemci Hatası', 'warning', 'İşlem için gereken ön koşul sağlanmadı.', 'Sayfayı yenileyip son haliyle tekrar deneyin.'),
            413 => self::item(413, 'Payload Too Large', 'Veri çok büyük', '4xx İstemci Hatası', 'warning', 'Gönderilen dosya veya istek gövdesi çok büyük.', 'Dosya boyutunu küçültün veya daha küçük parça gönderin.'),
            414 => self::item(414, 'URI Too Long', 'Adres çok uzun', '4xx İstemci Hatası', 'warning', 'URL güvenli sınırların üzerinde uzun.', 'Filtreleri azaltın veya işlemi form üzerinden gönderin.'),
            415 => self::item(415, 'Unsupported Media Type', 'Dosya türü desteklenmiyor', '4xx İstemci Hatası', 'warning', 'Gönderilen içerik türü desteklenmiyor.', 'Desteklenen formatları kullanın.'),
            416 => self::item(416, 'Range Not Satisfiable', 'Aralık uygun değil', '4xx İstemci Hatası', 'warning', 'İstenen dosya aralığı geçersiz.', 'İndirmeyi baştan başlatın veya bağlantıyı yenileyin.'),
            417 => self::item(417, 'Expectation Failed', 'Beklenti karşılanamadı', '4xx İstemci Hatası', 'warning', 'İstemcinin gönderdiği Expect başlığı karşılanamadı.', 'İstemci ayarlarınızı kontrol edin.'),
            418 => self::item(418, "I'm a teapot", 'Geçersiz işlem', '4xx İstemci Hatası', 'warning', 'Bu istek sistem tarafından geçerli işlem olarak kabul edilmedi.', 'İşlemi doğru ekrandan yeniden başlatın.'),
            419 => self::item(419, 'Page Expired', 'Oturum süresi doldu', '4xx İstemci Hatası', 'warning', 'Form güvenlik anahtarı veya oturum geçerliliğini kaybetti.', 'Sayfayı yenileyip işlemi yeniden başlatın.'),
            421 => self::item(421, 'Misdirected Request', 'Yanlış hedefli istek', '4xx İstemci Hatası', 'warning', 'İstek yanlış sunucu veya sanal hosta yönlendirildi.', 'Alan adı ve SSL yönlendirmesini kontrol edin.'),
            422 => self::item(422, 'Unprocessable Entity', 'Doğrulama hatası', '4xx İstemci Hatası', 'warning', 'Gönderilen veri işlenebilir formatta değil veya doğrulama hatası içeriyor.', 'Formdaki uyarıları düzeltip tekrar gönderin.'),
            423 => self::item(423, 'Locked', 'Kaynak kilitli', '4xx İstemci Hatası', 'warning', 'Bu kaynak geçici olarak kilitli.', 'İşlem tamamlanana kadar bekleyin.'),
            424 => self::item(424, 'Failed Dependency', 'Bağımlı işlem başarısız', '4xx İstemci Hatası', 'warning', 'Bu işlem başka bir işlemin başarısız olması nedeniyle tamamlanamadı.', 'Önce bağlı işlemi tamamlayın.'),
            425 => self::item(425, 'Too Early', 'İşlem için çok erken', '4xx İstemci Hatası', 'warning', 'İstek çok erken gönderildi ve tekrar saldırı riskine karşı reddedildi.', 'Kısa süre bekleyip tekrar deneyin.'),
            426 => self::item(426, 'Upgrade Required', 'Yükseltme gerekli', '4xx İstemci Hatası', 'warning', 'Bu işlem için daha yeni protokol veya güvenli bağlantı gerekiyor.', 'HTTPS ve güncel tarayıcı kullanın.'),
            428 => self::item(428, 'Precondition Required', 'Ön koşul gerekli', '4xx İstemci Hatası', 'warning', 'İşlem için koşullu istek başlığı gerekli.', 'Sayfayı yenileyip tekrar deneyin.'),
            429 => self::item(429, 'Too Many Requests', 'Çok fazla istek', '4xx İstemci Hatası', 'warning', 'Kısa sürede çok fazla istek gönderildi.', 'Güvenlik limiti için biraz bekleyip tekrar deneyin.'),
            431 => self::item(431, 'Request Header Fields Too Large', 'Başlık alanları çok büyük', '4xx İstemci Hatası', 'warning', 'İstek başlıkları güvenli sınırları aşıyor.', 'Tarayıcı çerezlerini azaltın veya yeniden giriş yapın.'),
            451 => self::item(451, 'Unavailable For Legal Reasons', 'Yasal nedenle erişilemez', '4xx İstemci Hatası', 'danger', 'Bu kaynak yasal nedenlerle gösterilemiyor.', 'Detay için destek ekibiyle iletişime geçin.'),

            500 => self::item(500, 'Internal Server Error', 'Sunucu hatası', '5xx Sunucu Hatası', 'danger', 'Sistemde beklenmeyen bir hata oluştu.', 'Hata UID bilgisini destek ekibiyle paylaşın.'),
            501 => self::item(501, 'Not Implemented', 'Özellik hazır değil', '5xx Sunucu Hatası', 'danger', 'Bu işlem sunucuda henüz desteklenmiyor.', 'Farklı bir işlem deneyin veya destek ekibine bildirin.'),
            502 => self::item(502, 'Bad Gateway', 'Geçersiz ağ geçidi', '5xx Sunucu Hatası', 'danger', 'Bağlı servislerden geçersiz yanıt alındı.', 'Kısa süre sonra tekrar deneyin.'),
            503 => self::item(503, 'Service Unavailable', 'Servis geçici olarak kullanılamıyor', '5xx Sunucu Hatası', 'danger', 'Sistem bakımda veya yoğunluk nedeniyle geçici olarak hizmet veremiyor.', 'Birkaç dakika sonra tekrar deneyin.'),
            504 => self::item(504, 'Gateway Timeout', 'Ağ geçidi zaman aşımı', '5xx Sunucu Hatası', 'danger', 'Bağlı servis beklenen sürede yanıt vermedi.', 'İşlemi tekrar deneyin; sipariş/ödeme durumunu iki kez kontrol edin.'),
            505 => self::item(505, 'HTTP Version Not Supported', 'HTTP sürümü desteklenmiyor', '5xx Sunucu Hatası', 'danger', 'İstemcinin kullandığı HTTP sürümü desteklenmiyor.', 'Güncel tarayıcı veya istemci kullanın.'),
            506 => self::item(506, 'Variant Also Negotiates', 'Sunucu varyant hatası', '5xx Sunucu Hatası', 'danger', 'Sunucu içerik varyantını çözemedi.', 'Destek ekibine hata UID bilgisini iletin.'),
            507 => self::item(507, 'Insufficient Storage', 'Depolama yetersiz', '5xx Sunucu Hatası', 'danger', 'Sunucuda işlem için yeterli depolama alanı yok.', 'Destek ekibine hata UID bilgisini iletin.'),
            508 => self::item(508, 'Loop Detected', 'Döngü algılandı', '5xx Sunucu Hatası', 'danger', 'Sunucu işlem sırasında döngü tespit etti.', 'İşlemi durdurup destek ekibine bildirin.'),
            511 => self::item(511, 'Network Authentication Required', 'Ağ kimliği gerekli', '5xx Sunucu Hatası', 'danger', 'Ağa erişmek için ek kimlik doğrulama gerekiyor.', 'Ağ bağlantınızı ve portal girişinizi kontrol edin.'),
        ];
    }

    /**
     * @return array{code:int, text:string, title:string, category:string, severity:string, message:string, recommendation:string}
     */
    public static function find(int $code): array
    {
        return self::all()[$code] ?? self::all()[500];
    }

    /**
     * @return array<int, array{code:int, text:string, title:string, category:string, severity:string, message:string, recommendation:string}>
     */
    public static function clientAndServerErrors(): array
    {
        return array_filter(self::all(), fn (array $item): bool => $item['code'] >= 400);
    }

    /**
     * @return array{code:int, text:string, title:string, category:string, severity:string, message:string, recommendation:string}
     */
    protected static function item(int $code, string $text, string $title, string $category, string $severity, string $message, string $recommendation): array
    {
        return compact('code', 'text', 'title', 'category', 'severity', 'message', 'recommendation');
    }
}
