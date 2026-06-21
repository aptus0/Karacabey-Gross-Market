export type HttpStatusMeta = {
  code: number;
  text: string;
  title: string;
  category: string;
  description: string;
  recommendation: string;
};

export const httpStatusCatalog: Record<number, HttpStatusMeta> = {
  100: { code: 100, text: "Continue", title: "İstek devam ediyor", category: "1xx Bilgilendirme", description: "Sunucu isteğin ilk bölümünü aldı ve devam edebilirsiniz.", recommendation: "İstek otomatik olarak devam edecektir." },
  101: { code: 101, text: "Switching Protocols", title: "Protokol değiştiriliyor", category: "1xx Bilgilendirme", description: "Sunucu bağlantı protokolünü değiştirmeyi kabul etti.", recommendation: "Bağlantı otomatik olarak yeni protokole geçer." },
  102: { code: 102, text: "Processing", title: "İstek işleniyor", category: "1xx Bilgilendirme", description: "İstek alındı ve arka planda işleniyor.", recommendation: "Aynı işlemi art arda tekrar göndermeyin." },
  103: { code: 103, text: "Early Hints", title: "Ön yükleme bilgisi gönderildi", category: "1xx Bilgilendirme", description: "Tarayıcıya erken yükleme ipuçları gönderildi.", recommendation: "Kullanıcı aksiyonu gerekmez." },
  200: { code: 200, text: "OK", title: "İşlem başarılı", category: "2xx Başarı", description: "İstek başarıyla tamamlandı.", recommendation: "Sayfayı kullanmaya devam edebilirsiniz." },
  201: { code: 201, text: "Created", title: "Kayıt oluşturuldu", category: "2xx Başarı", description: "Yeni kayıt başarıyla oluşturuldu.", recommendation: "Oluşturulan kaydı kontrol edebilirsiniz." },
  202: { code: 202, text: "Accepted", title: "İstek kabul edildi", category: "2xx Başarı", description: "İstek kabul edildi ve sıraya alındı.", recommendation: "Durum ekranından takip edin." },
  203: { code: 203, text: "Non-Authoritative Information", title: "Kaynak dışı bilgi", category: "2xx Başarı", description: "Yanıt başarıyla geldi ancak ara kaynak üzerinden üretildi.", recommendation: "Kritik veriyi ana kaynaktan doğrulayın." },
  204: { code: 204, text: "No Content", title: "İçerik yok", category: "2xx Başarı", description: "İşlem başarılı, ancak gösterilecek içerik yok.", recommendation: "Önceki ekrana dönebilirsiniz." },
  205: { code: 205, text: "Reset Content", title: "Form sıfırlanmalı", category: "2xx Başarı", description: "İşlem başarılı ve formun temizlenmesi gerekiyor.", recommendation: "Yeni işlem başlatabilirsiniz." },
  206: { code: 206, text: "Partial Content", title: "Kısmi içerik", category: "2xx Başarı", description: "İçeriğin sadece istenen bölümü gönderildi.", recommendation: "Yükleme devam ediyorsa bekleyin." },
  300: { code: 300, text: "Multiple Choices", title: "Birden fazla seçenek", category: "3xx Yönlendirme", description: "İstek için birden fazla uygun hedef bulundu.", recommendation: "Doğru hedefi seçin." },
  301: { code: 301, text: "Moved Permanently", title: "Kalıcı yönlendirme", category: "3xx Yönlendirme", description: "Sayfa kalıcı olarak taşındı.", recommendation: "Yeni bağlantıyı kullanın." },
  302: { code: 302, text: "Found", title: "Geçici yönlendirme", category: "3xx Yönlendirme", description: "Sayfa geçici olarak başka adrese yönlendiriliyor.", recommendation: "Yönlendirme tamamlanmazsa yenileyin." },
  303: { code: 303, text: "See Other", title: "Başka adrese bakın", category: "3xx Yönlendirme", description: "İşlem sonucu farklı bir adreste görüntülenmeli.", recommendation: "Yönlendirilen sayfadan devam edin." },
  304: { code: 304, text: "Not Modified", title: "İçerik değişmedi", category: "3xx Yönlendirme", description: "İçerik son ziyaretten beri değişmedi.", recommendation: "Önbellekteki içerik kullanılabilir." },
  307: { code: 307, text: "Temporary Redirect", title: "Geçici yönlendirme", category: "3xx Yönlendirme", description: "İstek geçici olarak farklı adrese yönlendirildi.", recommendation: "İşlem metodunuz korunur." },
  308: { code: 308, text: "Permanent Redirect", title: "Kalıcı yönlendirme", category: "3xx Yönlendirme", description: "Kaynak kalıcı olarak yeni adrese taşındı.", recommendation: "Yeni adresi kullanmaya devam edin." },
  400: { code: 400, text: "Bad Request", title: "Geçersiz istek", category: "4xx İstemci Hatası", description: "Gönderilen istek okunamadı veya eksik.", recommendation: "Form alanlarını ve bağlantıyı kontrol edin." },
  401: { code: 401, text: "Unauthorized", title: "Oturum gerekli", category: "4xx İstemci Hatası", description: "Bu işlem için giriş yapmanız gerekiyor.", recommendation: "Hesabınıza giriş yapıp tekrar deneyin." },
  402: { code: 402, text: "Payment Required", title: "Ödeme gerekli", category: "4xx İstemci Hatası", description: "Bu işlem için ödeme veya ödeme onayı gerekiyor.", recommendation: "Sepetinizi ve ödeme durumunuzu kontrol edin." },
  403: { code: 403, text: "Forbidden", title: "Erişim reddedildi", category: "4xx İstemci Hatası", description: "Bu kaynağa erişim yetkiniz yok.", recommendation: "Yetki gerekiyorsa destek ekibiyle iletişime geçin." },
  404: { code: 404, text: "Not Found", title: "Sayfa bulunamadı", category: "4xx İstemci Hatası", description: "Aradığınız sayfa veya kayıt bulunamadı.", recommendation: "Bağlantıyı kontrol edin veya ana sayfadan tekrar arayın." },
  405: { code: 405, text: "Method Not Allowed", title: "Metot desteklenmiyor", category: "4xx İstemci Hatası", description: "Bu adres için kullanılan HTTP metodu geçerli değil.", recommendation: "İşlemi doğru ekrandan başlatın." },
  406: { code: 406, text: "Not Acceptable", title: "Yanıt formatı uygun değil", category: "4xx İstemci Hatası", description: "İstenen yanıt formatı desteklenmiyor.", recommendation: "Tarayıcı veya istemci ayarlarınızı kontrol edin." },
  407: { code: 407, text: "Proxy Authentication Required", title: "Proxy kimliği gerekli", category: "4xx İstemci Hatası", description: "Ağ geçidi veya proxy kimlik doğrulaması istiyor.", recommendation: "Ağ/proxy hesabınızı kontrol edin." },
  408: { code: 408, text: "Request Timeout", title: "İstek zaman aşımı", category: "4xx İstemci Hatası", description: "İstek beklenen sürede tamamlanamadı.", recommendation: "Bağlantınızı kontrol edip tekrar deneyin." },
  409: { code: 409, text: "Conflict", title: "Çakışma oluştu", category: "4xx İstemci Hatası", description: "İşlem mevcut durumla çakışıyor.", recommendation: "Sayfayı yenileyip tekrar deneyin." },
  410: { code: 410, text: "Gone", title: "İçerik kaldırıldı", category: "4xx İstemci Hatası", description: "Bu kaynak artık yayında değil.", recommendation: "Benzer içerikleri arayın." },
  411: { code: 411, text: "Length Required", title: "İçerik uzunluğu gerekli", category: "4xx İstemci Hatası", description: "İstek gövdesi için uzunluk bilgisi gerekli.", recommendation: "İstemcinizin Content-Length gönderdiğinden emin olun." },
  412: { code: 412, text: "Precondition Failed", title: "Ön koşul başarısız", category: "4xx İstemci Hatası", description: "Gereken ön koşul sağlanmadı.", recommendation: "Sayfayı yenileyip tekrar deneyin." },
  413: { code: 413, text: "Payload Too Large", title: "Veri çok büyük", category: "4xx İstemci Hatası", description: "Gönderilen dosya veya veri çok büyük.", recommendation: "Dosya boyutunu küçültün." },
  414: { code: 414, text: "URI Too Long", title: "Adres çok uzun", category: "4xx İstemci Hatası", description: "URL güvenli sınırların üzerinde uzun.", recommendation: "Filtreleri azaltın." },
  415: { code: 415, text: "Unsupported Media Type", title: "Dosya türü desteklenmiyor", category: "4xx İstemci Hatası", description: "Gönderilen içerik türü desteklenmiyor.", recommendation: "Desteklenen formatları kullanın." },
  416: { code: 416, text: "Range Not Satisfiable", title: "Aralık uygun değil", category: "4xx İstemci Hatası", description: "İstenen dosya aralığı geçersiz.", recommendation: "İndirmeyi baştan başlatın." },
  417: { code: 417, text: "Expectation Failed", title: "Beklenti karşılanamadı", category: "4xx İstemci Hatası", description: "İstemcinin gönderdiği Expect başlığı karşılanamadı.", recommendation: "İstemci ayarlarınızı kontrol edin." },
  418: { code: 418, text: "I'm a teapot", title: "Geçersiz işlem", category: "4xx İstemci Hatası", description: "Bu istek geçerli işlem olarak kabul edilmedi.", recommendation: "İşlemi doğru ekrandan başlatın." },
  419: { code: 419, text: "Page Expired", title: "Oturum süresi doldu", category: "4xx İstemci Hatası", description: "Form güvenlik anahtarı veya oturum geçerliliğini kaybetti.", recommendation: "Sayfayı yenileyip işlemi yeniden başlatın." },
  421: { code: 421, text: "Misdirected Request", title: "Yanlış hedefli istek", category: "4xx İstemci Hatası", description: "İstek yanlış sunucuya yönlendirildi.", recommendation: "Alan adı ve SSL yönlendirmesini kontrol edin." },
  422: { code: 422, text: "Unprocessable Entity", title: "Doğrulama hatası", category: "4xx İstemci Hatası", description: "Gönderilen veri doğrulama hatası içeriyor.", recommendation: "Formdaki uyarıları düzeltin." },
  423: { code: 423, text: "Locked", title: "Kaynak kilitli", category: "4xx İstemci Hatası", description: "Bu kaynak geçici olarak kilitli.", recommendation: "İşlem tamamlanana kadar bekleyin." },
  424: { code: 424, text: "Failed Dependency", title: "Bağımlı işlem başarısız", category: "4xx İstemci Hatası", description: "Bağlı işlem başarısız olduğu için tamamlanamadı.", recommendation: "Önce bağlı işlemi tamamlayın." },
  425: { code: 425, text: "Too Early", title: "İşlem için çok erken", category: "4xx İstemci Hatası", description: "İstek çok erken gönderildi ve reddedildi.", recommendation: "Kısa süre bekleyip tekrar deneyin." },
  426: { code: 426, text: "Upgrade Required", title: "Yükseltme gerekli", category: "4xx İstemci Hatası", description: "Daha yeni protokol veya güvenli bağlantı gerekiyor.", recommendation: "HTTPS ve güncel tarayıcı kullanın." },
  428: { code: 428, text: "Precondition Required", title: "Ön koşul gerekli", category: "4xx İstemci Hatası", description: "İşlem için koşullu istek başlığı gerekli.", recommendation: "Sayfayı yenileyip tekrar deneyin." },
  429: { code: 429, text: "Too Many Requests", title: "Çok fazla istek", category: "4xx İstemci Hatası", description: "Kısa sürede çok fazla istek gönderildi.", recommendation: "Biraz bekleyip tekrar deneyin." },
  431: { code: 431, text: "Request Header Fields Too Large", title: "Başlıklar çok büyük", category: "4xx İstemci Hatası", description: "İstek başlıkları güvenli sınırları aşıyor.", recommendation: "Çerezleri azaltın veya yeniden giriş yapın." },
  451: { code: 451, text: "Unavailable For Legal Reasons", title: "Yasal nedenle erişilemez", category: "4xx İstemci Hatası", description: "Bu kaynak yasal nedenlerle gösterilemiyor.", recommendation: "Detay için destek ekibiyle iletişime geçin." },
  500: { code: 500, text: "Internal Server Error", title: "Sunucu hatası", category: "5xx Sunucu Hatası", description: "Sistemde beklenmeyen bir hata oluştu.", recommendation: "Hata UID bilgisini destek ekibiyle paylaşın." },
  501: { code: 501, text: "Not Implemented", title: "Özellik hazır değil", category: "5xx Sunucu Hatası", description: "Bu işlem sunucuda henüz desteklenmiyor.", recommendation: "Farklı bir işlem deneyin." },
  502: { code: 502, text: "Bad Gateway", title: "Geçersiz ağ geçidi", category: "5xx Sunucu Hatası", description: "Bağlı servislerden geçersiz yanıt alındı.", recommendation: "Kısa süre sonra tekrar deneyin." },
  503: { code: 503, text: "Service Unavailable", title: "Servis kullanılamıyor", category: "5xx Sunucu Hatası", description: "Sistem bakımda veya yoğunluk nedeniyle hizmet veremiyor.", recommendation: "Birkaç dakika sonra tekrar deneyin." },
  504: { code: 504, text: "Gateway Timeout", title: "Ağ geçidi zaman aşımı", category: "5xx Sunucu Hatası", description: "Bağlı servis beklenen sürede yanıt vermedi.", recommendation: "İşlemi tekrar deneyin; ödeme durumunu kontrol edin." },
  505: { code: 505, text: "HTTP Version Not Supported", title: "HTTP sürümü desteklenmiyor", category: "5xx Sunucu Hatası", description: "Kullanılan HTTP sürümü desteklenmiyor.", recommendation: "Güncel tarayıcı veya istemci kullanın." },
  506: { code: 506, text: "Variant Also Negotiates", title: "Sunucu varyant hatası", category: "5xx Sunucu Hatası", description: "Sunucu içerik varyantını çözemedi.", recommendation: "Hata UID bilgisini destek ekibine iletin." },
  507: { code: 507, text: "Insufficient Storage", title: "Depolama yetersiz", category: "5xx Sunucu Hatası", description: "Sunucuda yeterli depolama alanı yok.", recommendation: "Hata UID bilgisini destek ekibine iletin." },
  508: { code: 508, text: "Loop Detected", title: "Döngü algılandı", category: "5xx Sunucu Hatası", description: "Sunucu işlem sırasında döngü tespit etti.", recommendation: "İşlemi durdurup destek ekibine bildirin." },
  511: { code: 511, text: "Network Authentication Required", title: "Ağ kimliği gerekli", category: "5xx Sunucu Hatası", description: "Ağa erişmek için ek kimlik doğrulama gerekiyor.", recommendation: "Ağ bağlantınızı kontrol edin." },
};

export function getHttpStatusMeta(code: number): HttpStatusMeta {
  return httpStatusCatalog[code] ?? httpStatusCatalog[500];
}

export const supportedHttpStatusCodes = Object.keys(httpStatusCatalog).map(Number).sort((a, b) => a - b);
