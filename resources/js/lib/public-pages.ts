export type PublicPageData = {
  slug: string;
  title: string;
  eyebrow: string;
  description: string;
  sections: Array<{
    title: string;
    body: string;
  }>;
  links?: Array<{ label: string; href: string }>;
  seo: {
    title: string;
    description: string;
    keywords: string[];
  };
};

export const publicPages: PublicPageData[] = [
  {
    slug: "hakkimizda",
    title: "Hakkımızda",
    eyebrow: "Kurumsal",
    description: "Karacabey Gross Market; Karacabey/Bursa’da gıda, temizlik, şarküteri, meyve-sebze, içecek ve günlük market ihtiyaçlarını web ve mobil kanalda sunan yerel online markettir.",
    sections: [
      { title: "Yerel Mağaza", body: "Drama Mahallesi, Runguçpaşa Caddesi, 16700 Karacabey/Bursa bölgesinde hizmet veren mağaza deneyimimizi karacabeygrossmarket.com üzerinden online alışverişe taşıyoruz." },
      { title: "Ürün Çeşitliliği", body: "Temel gıda, meyve ve sebze, şarküteri, süt ve kahvaltılık, et-tavuk-balık, atıştırmalık, içecek, temizlik ve kişisel bakım kategorilerinde güncel ürün kataloğu sunulur." },
      { title: "Web ve Mobil Deneyim", body: "Web sitesi ve iOS mobil uygulama aynı hesap, aynı sepet, favoriler, adresler, sipariş geçmişi ve bildirim altyapısıyla birlikte çalışacak şekilde tasarlanmıştır." },
      { title: "Güvenli Alışveriş", body: "Ödeme akışı PayTR güvenli ödeme altyapısıyla yönetilir; kart bilgileri mağaza sisteminde tutulmaz. Sipariş, ödeme ve teslimat adımları kayıt altına alınır." },
      { title: "Karacabey Teslimat Odaklı", body: "Teslimat bölgesi Karacabey odaklı planlanır. Minimum sepet, stok, kargo ve teslimat uygunluğu checkout sırasında sunucu tarafında doğrulanır." },
    ],
    seo: {
      title: "Hakkımızda | Karacabey Gross Market Karacabey Online Market",
      description: "Karacabey Gross Market hakkında bilgiler: Karacabey/Bursa yerel online market, ürün kategorileri, güvenli ödeme, mobil uygulama ve teslimat yaklaşımı.",
      keywords: ["hakkımızda", "karacabey gross market", "online market karacabey", "karacabey bursa market", "drama mahallesi market", "runguçpaşa caddesi market", "karacabey market mobil uygulama", "karacabey market siparişi"],
    },
  },
  {
    slug: "iletisim",
    title: "İletişim",
    eyebrow: "Destek",
    description: "Karacabey Gross Market mağaza adresi, telefon, e-posta ve online market destek kanalları.",
    sections: [
      { title: "Telefon", body: "(0224) 676 84 33" },
      { title: "E-posta", body: "destek@karacabeygrossmarket.com" },
      { title: "Mağaza Adresi", body: "Drama Mahallesi, Runguçpaşa Caddesi, 16700 Karacabey/Bursa" },
      { title: "Hizmet Bölgesi", body: "Online sipariş ve teslimat akışı öncelikli olarak Karacabey merkezi ve uygun mahalleler için planlanır." },
      { title: "Destek Konuları", body: "Sipariş onayı, ödeme durumu, teslimat, ürün iadesi, fatura, hesap ve mobil uygulama bildirimleri için destek alabilirsiniz." },
    ],
    links: [
      { label: "E-posta Gönder", href: "mailto:destek@karacabeygrossmarket.com" },
      { label: "Yardım Merkezi", href: "/yardim" },
    ],
    seo: {
      title: "İletişim | Karacabey Gross Market",
      description: "Karacabey Gross Market iletişim bilgileri: Drama Mahallesi Runguçpaşa Caddesi Karacabey/Bursa adresi, telefon, e-posta ve destek kanalları.",
      keywords: ["iletişim", "karacabey gross iletişim", "karacabey gross market telefon", "karacabey gross market adres", "drama mahallesi market", "runguçpaşa caddesi market", "market destek"],
    },
  },
  {
    slug: "yardim",
    title: "Yardım Merkezi",
    eyebrow: "Yardım",
    description: "Alışveriş, teslimat, ödeme ve hesap işlemleri için kısa yardım başlıkları.",
    sections: [
      { title: "Sipariş", body: "Sepetini kontrol edip checkout ekranından teslimat ve ödeme adımlarını tamamlayabilirsin." },
      { title: "Kargo", body: "Standart gönderiler için kargo hesaplama sayfasından yaklaşık tutar görebilirsin." },
      { title: "Hesap", body: "Adres, sipariş ve bildirim işlemlerini Hesabım alanından yönetebilirsin." },
    ],
    links: [
      { label: "Kargo Hesaplama", href: "/kargo-hesaplama" },
      { label: "Hesabım", href: "/account" },
    ],
    seo: {
      title: "Yardım Merkezi | Karacabey Gross Market",
      description: "Karacabey Gross Market alışveriş, kargo, ödeme ve hesap yardım merkezi.",
      keywords: ["yardım merkezi", "market yardım", "sipariş destek"],
    },
  },
  {
    slug: "sikca-sorulan-sorular",
    title: "Sıkça Sorulan Sorular",
    eyebrow: "SSS",
    description: "Sipariş, ödeme, teslimat, hesap ve mobil uygulama hakkında müşterilerin en sık ihtiyaç duyduğu kısa cevaplar.",
    sections: [
      { title: "Karacabey Gross Market nedir?", body: "Karacabey Gross Market, Karacabey ve Bursa çevresine taze ürün, şarküteri, kuruyemiş, içecek ve temizlik kategorilerinde online market hizmeti sunan yerel bir platformdur." },
      { title: "Minimum sipariş tutarı nedir?", body: "Standart kargo akışında minimum ödeme tutarı 350 TL olarak planlanmıştır." },
      { title: "Kargo ne zaman ücretsiz olur?", body: "1500 TL ve üzeri standart gönderilerde kargo ücretsiz hesaplanır." },
      { title: "Aynı gün teslimat var mı?", body: "Karacabey merkez ve yakın bölgelerde uygun saatler dahilinde aynı gün teslimat planlanır. Uzak bölgelerde standart kargo süresi 1–3 iş günüdür." },
      { title: "Ödeme nasıl alınır?", body: "Ödeme PayTR güvenli ödeme sayfası üzerinden tamamlanır; kart bilgisi mağaza sisteminde tutulmaz. Visa, Mastercard, American Express, Bankkart ve yemek kartları (Sodexo, Multinet, Edenred, MetropolCard) desteklenir." },
      { title: "Hangi yemek kartlarını kabul ediyorsunuz?", body: "Sodexo, Multinet, Edenred Ticket ve MetropolCard yemek kartlarını online ödeme akışında kabul ediyoruz." },
      { title: "Karacabey Gross Market mobil uygulaması var mı?", body: "Evet, iOS uygulamamız geliştirme aşamasında olup App Store üzerinden yayınlanacaktır. Mobil tarayıcıdan da tüm özellikleri kullanabilirsiniz; ayrıntılar için /mobile sayfasını ziyaret edebilirsiniz." },
      { title: "Mobil uygulamayı nasıl indirebilirim?", body: "Uygulama lansmandan sonra App Store üzerinden ücretsiz olarak indirilebilecek. TestFlight beta programına kayıt için /mobile sayfasından bildirim aboneliği oluşturabilirsiniz." },
      { title: "Hesabımı web ve mobilde aynı anda kullanabilir miyim?", body: "Evet, aynı hesap web sitesi ve mobil uygulama arasında senkron çalışır. Sepet, favoriler, adresler ve siparişler her iki platformda da görünür." },
      { title: "Siparişimi nasıl takip edebilirim?", body: "Sipariş onayından sonra hesabım > siparişlerim alanından durumunu izleyebilir, kargo takip sayfasından (/kargo-takip) güncel hareketleri görebilirsiniz." },
      { title: "İade ve iptal nasıl yapılır?", body: "Teslim tarihinden itibaren 14 gün içinde iade talebi oluşturulabilir. İade kargosu mağaza tarafından karşılanır. Detaylar iade ve iptal koşulları sayfasında yer alır." },
      { title: "Kurumsal sipariş veriyor musunuz?", body: "Evet, firma fatura bilgileri ile checkout adımında kurumsal sipariş oluşturulabilir. Düzenli toplu alımlar için iletişim sayfasından bizimle görüşebilirsiniz." },
      { title: "Mağaza adresi ve telefonu nedir?", body: "Drama Mahallesi, Runguçpaşa Caddesi, 16700 Karacabey/Bursa adresindeyiz. Telefon: (0224) 676 84 33. Çalışma saatleri her gün 09:00–21:00." },
    ],
    seo: {
      title: "Sıkça Sorulan Sorular | Karacabey Gross Market",
      description: "Karacabey Gross Market sipariş, ödeme, teslimat, iade, hesap ve mobil uygulama (iOS) sıkça sorulan sorular ve kısa cevapları.",
      keywords: [
        "sıkça sorulan sorular",
        "karacabey gross market sss",
        "karacabey market mobil uygulama",
        "ios market uygulaması",
        "kargo soruları",
        "ödeme soruları",
        "iade soruları",
        "minimum sipariş tutarı",
        "yemek kartı market",
      ],
    },
  },
  {
    slug: "kurumsal-siparis",
    title: "Kurumsal Sipariş",
    eyebrow: "Kurumsal",
    description: "Firma alışverişleri için fatura bilgileri, teslimat adresi ve sipariş notu birlikte alınır.",
    sections: [
      { title: "Firma Bilgisi", body: "Checkout ekranında firma ünvanı, vergi dairesi ve vergi numarası alanları bulunur." },
      { title: "Toplu Sipariş", body: "Düzenli alımlar için kayıtlı adres ve sipariş geçmişi üzerinden hızlı tekrar sipariş verilebilir." },
    ],
    links: [{ label: "Checkout'a Git", href: "/checkout" }],
    seo: {
      title: "Kurumsal Sipariş | Karacabey Gross Market",
      description: "Karacabey Gross Market kurumsal sipariş, firma faturası ve toplu alışveriş bilgileri.",
      keywords: ["kurumsal sipariş", "firma market alışverişi", "vergi numarası"],
    },
  },
  {
    slug: "teslimat-bolgeleri",
    title: "Teslimat Bölgeleri",
    eyebrow: "Teslimat",
    description: "Aktif teslimat bölgeleri ve uygunluk bilgileri checkout sırasında adres seçimine göre hesaplanır.",
    sections: [
      { title: "Yerel Bölge", body: "Bursa / Karacabey seçildiğinde yerel teslimat akışı kullanılır." },
      { title: "Standart Kargo", body: "Karacabey dışı gönderilerde seçilen taşıyıcı ve sipariş tutarı dikkate alınır." },
    ],
    links: [{ label: "Kargo Hesaplama", href: "/kargo-hesaplama" }],
    seo: {
      title: "Teslimat Bölgeleri | Karacabey Gross Market",
      description: "Karacabey Gross Market teslimat bölgeleri, yerel teslimat ve standart kargo bilgileri.",
      keywords: ["teslimat bölgeleri", "karacabey teslimat", "bursa market teslimat"],
    },
  },
  {
    slug: "teslimat-kosullari",
    title: "Teslimat Koşulları",
    eyebrow: "Teslimat",
    description: "Teslimat koşulları sipariş tutarı, adres ve seçilen kargo firmasına göre değişir.",
    sections: [
      { title: "Minimum Tutar", body: "Standart kargo akışında minimum ödeme tutarı 350 TL olarak belirlenmiştir." },
      { title: "Ücretsiz Kargo", body: "1500 TL ve üzeri standart gönderilerde kargo ücretsiz hesaplanır." },
      { title: "Yerel Teslimat", body: "Bursa / Karacabey adreslerinde standart kargo kuralı uygulanmaz." },
    ],
    links: [{ label: "Kargo Hesaplama", href: "/kargo-hesaplama" }],
    seo: {
      title: "Teslimat Koşulları | Karacabey Gross Market",
      description: "Minimum sipariş, ücretsiz kargo ve teslimat koşulları.",
      keywords: ["teslimat koşulları", "ücretsiz kargo", "minimum sipariş"],
    },
  },
  {
    slug: "kullanim-kosullari",
    title: "Kullanım Koşulları",
    eyebrow: "Yasal",
    description: "Web sitesi ve mobil alışveriş hizmetlerinin kullanım şartlarını özetler.",
    sections: [
      { title: "Hesap Kullanımı", body: "Kullanıcı hesap bilgilerini güncel ve doğru tutmakla sorumludur." },
      { title: "Sipariş", body: "Siparişler stok, ödeme ve teslimat uygunluğuna göre işlenir." },
    ],
    seo: {
      title: "Kullanım Koşulları | Karacabey Gross Market",
      description: "Karacabey Gross Market web ve mobil kullanım koşulları.",
      keywords: ["kullanım koşulları", "üyelik şartları", "site kuralları"],
    },
  },
  {
    slug: "gizlilik-politikasi",
    title: "Gizlilik Politikası",
    eyebrow: "Yasal",
    description: "Kişisel verilerin hangi amaçlarla işlendiğini ve nasıl korunduğunu açıklar.",
    sections: [
      { title: "Veri Kullanımı", body: "Sipariş, teslimat, ödeme ve destek süreçlerinde gerekli bilgiler kullanılır." },
      { title: "Güvenlik", body: "Hassas ödeme bilgileri mağaza sisteminde saklanmaz; ödeme sağlayıcı güvenli ekranında işlenir." },
    ],
    seo: {
      title: "Gizlilik Politikası | Karacabey Gross Market",
      description: "Karacabey Gross Market gizlilik politikası ve veri güvenliği yaklaşımı.",
      keywords: ["gizlilik politikası", "veri güvenliği", "kişisel veri"],
    },
  },
  {
    slug: "kvkk",
    title: "KVKK Aydınlatma Metni",
    eyebrow: "Yasal",
    description: "Kişisel verilerin işlenmesi, saklanması ve korunmasına dair bilgilendirme.",
    sections: [
      { title: "Amaç", body: "Veriler sipariş, teslimat, müşteri hizmetleri ve yasal yükümlülükler için işlenir." },
      { title: "Haklar", body: "KVKK kapsamındaki başvurular destek kanalı üzerinden iletilebilir." },
    ],
    seo: {
      title: "KVKK Aydınlatma Metni | Karacabey Gross Market",
      description: "Karacabey Gross Market KVKK aydınlatma metni.",
      keywords: ["kvkk", "aydınlatma metni", "kişisel veriler"],
    },
  },
  {
    slug: "cerez-politikasi",
    title: "Çerez Politikası",
    eyebrow: "Yasal",
    description: "Çerezler oturum, güvenlik, tercih ve ölçüm amaçlarıyla kullanılabilir.",
    sections: [
      { title: "Zorunlu Çerezler", body: "Oturum, sepet ve güvenlik işlemleri için gerekli çerezler kullanılır." },
      { title: "Tercihler", body: "Analitik ve pazarlama çerezleri tercih yönetimine göre sınırlandırılabilir." },
    ],
    seo: {
      title: "Çerez Politikası | Karacabey Gross Market",
      description: "Karacabey Gross Market çerez politikası ve çerez kullanım amacı.",
      keywords: ["çerez politikası", "cookie", "çerez yönetimi"],
    },
  },
  {
    slug: "mesafeli-satis-sozlesmesi",
    title: "Mesafeli Satış Sözleşmesi",
    eyebrow: "Yasal",
    description: "Online siparişlerde uygulanacak temel satış ve teslimat hükümleri.",
    sections: [
      { title: "Sipariş Onayı", body: "Sipariş özeti checkout ekranında kullanıcıya gösterilir ve ödeme sonrası işleme alınır." },
      { title: "Teslimat", body: "Teslimat, adres uygunluğu ve taşıyıcı akışına göre tamamlanır." },
    ],
    seo: {
      title: "Mesafeli Satış Sözleşmesi | Karacabey Gross Market",
      description: "Karacabey Gross Market mesafeli satış sözleşmesi.",
      keywords: ["mesafeli satış sözleşmesi", "online satış", "tüketici hakları"],
    },
  },
  {
    slug: "on-bilgilendirme-formu",
    title: "Ön Bilgilendirme Formu",
    eyebrow: "Yasal",
    description: "Sipariş öncesinde ürün, fiyat, teslimat ve ödeme bilgilerinin özetlendiği formdur.",
    sections: [
      { title: "Ürün ve Fiyat", body: "Ürün fiyatı, miktar ve sepet toplamı checkout ekranında gösterilir." },
      { title: "Teslimat", body: "Teslimat bilgileri adres ve kargo seçimine göre hesaplanır." },
    ],
    seo: {
      title: "Ön Bilgilendirme Formu | Karacabey Gross Market",
      description: "Karacabey Gross Market ön bilgilendirme formu.",
      keywords: ["ön bilgilendirme formu", "sipariş bilgisi", "checkout bilgisi"],
    },
  },
  {
    slug: "iade-ve-iptal-kosullari",
    title: "İade ve İptal Koşulları",
    eyebrow: "Yasal",
    description: "İade ve iptal talepleri ürün tipi, teslimat durumu ve ödeme sürecine göre değerlendirilir.",
    sections: [
      { title: "İptal", body: "Hazırlık aşamasına geçmeyen siparişlerde iptal talebi destek kanalı üzerinden alınır." },
      { title: "İade", body: "İade talepleri ürün niteliği ve yasal koşullara göre incelenir." },
    ],
    seo: {
      title: "İade ve İptal Koşulları | Karacabey Gross Market",
      description: "Karacabey Gross Market iade ve iptal koşulları.",
      keywords: ["iade koşulları", "iptal koşulları", "market iade"],
    },
  },
  {
    slug: "teslimat-ve-kargo",
    title: "Teslimat ve Kargo Politikası",
    eyebrow: "Yasal",
    description: "Teslimat, kargo seçimi ve taşıyıcı süreçlerine dair genel politika.",
    sections: [
      { title: "Kargo Firmaları", body: "Aras Kargo, Yurtiçi Kargo, PTT ve DHL eCommerce entegrasyonları desteklenecek şekilde yapılandırılır." },
      { title: "Ücret", body: "Kargo ücreti sipariş tutarı, adres ve taşıyıcı seçimine göre hesaplanır." },
    ],
    links: [{ label: "Kargo Hesaplama", href: "/kargo-hesaplama" }],
    seo: {
      title: "Teslimat ve Kargo Politikası | Karacabey Gross Market",
      description: "Karacabey Gross Market teslimat ve kargo politikası.",
      keywords: ["teslimat ve kargo", "kargo politikası", "kargo hesaplama"],
    },
  },
];

export function findPublicPage(slug: string) {
  return publicPages.find((page) => page.slug === slug);
}
