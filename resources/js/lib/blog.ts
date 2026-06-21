export type BlogPost = {
  slug: string;
  title: string;
  excerpt: string;
  category: string;
  subcategories: string[];
  publishedAt: string;
  readTime: string;
  heroImage: string;
  content: string[];
  products: {
    name: string;
    slug: string;
    category: string;
    priceRange: string;
    unit: string;
    keywords: string[];
  }[];
  seo: {
    title: string;
    description: string;
    keywords: string[];
    ogTitle: string;
    ogDescription: string;
    canonicalUrl: string;
    schemaType: string;
    breadcrumbs: { name: string; url: string }[];
  };
};


export const blogPosts: BlogPost[] = [
  {
    slug: "karacabey-gross-alisveris-listesi-nasil-planlanir",
    title: "Karacabey gross alışveriş listesi nasıl daha verimli planlanır?",
    excerpt: "Haftalık siparişleri daha az tekrar, daha güçlü stok kontrolü ve daha net bütçe ile yönetmek için pratik bir çerçeve.",
    category: "Sipariş Planlama",
    subcategories: ["Haftalık Market", "Stok Yönetimi", "Aile Alışverişi"],
    publishedAt: "2026-04-27",
    readTime: "4 dk",
    heroImage: "https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=1400&q=80",
    content: [
      "Haftalık market planı hazırlanırken ilk adım tüketim ritmini doğru okumaktır. Süt, kahvaltılık, temizlik ve hızlı dönen temel gıda kalemleri aynı torbaya atıldığında siparişler ya gereğinden sıklaşır ya da son gün sürpriz stok açıkları oluşur.",
      "En pratik yöntem, ürünleri teslimat hassasiyetine göre katmanlamaktır. Soğuk zincir ürünler, taze ürünler ve uzun ömürlü stok kalemleri ayrı gruplandığında checkout anı daha kontrollü hale gelir.",
      "Kurumsal veya yoğun aile alışverişlerinde favori ürün listesini hazır sepetle birlikte kullanmak ciddi zaman kazandırır. Aynı ürünler tekrar tekrar aranmaz, yalnız eksik kalanlar güncellenir.",
    ],
    products: [
      { name: "Tam Yağlı Süt", slug: "tam-yagli-sut-1lt", category: "Süt ve Kahvaltılık", priceRange: "35-45 TL", unit: "1 Litre", keywords: ["süt", "tam yağlı süt", "günlük süt", "karacabey süt"] },
      { name: "Yumurta 30'lu", slug: "yumurta-30lu", category: "Süt ve Kahvaltılık", priceRange: "120-150 TL", unit: "30 Adet", keywords: ["yumurta", "köy yumurtası", "gross yumurta", "30lu yumurta"] },
      { name: "Tereyağ 500gr", slug: "tereyag-500gr", category: "Süt ve Kahvaltılık", priceRange: "180-220 TL", unit: "500 Gram", keywords: ["tereyağ", "doğal tereyağ", "köy tereyağı", "kahvaltılık yağ"] },
      { name: "Zeytin 1kg", slug: "zeytin-1kg", category: "Kahvaltılık", priceRange: "150-200 TL", unit: "1 Kilogram", keywords: ["zeytin", "siyah zeytin", "yeşil zeytin", "kahvaltılık zeytin"] },
      { name: "Sıvı Sabun 5lt", slug: "sivi-sabun-5lt", category: "Temizlik", priceRange: "180-250 TL", unit: "5 Litre", keywords: ["sıvı sabun", "gross sabun", "el sabunu", "ekonomik sabun"] },
      { name: "Çamaşır Deterjanı 5kg", slug: "camasir-deterjani-5kg", category: "Temizlik", priceRange: "250-350 TL", unit: "5 Kilogram", keywords: ["çamaşır deterjanı", "toz deterjan", "gross deterjan", "ekonomik deterjan"] },
    ],
    seo: {
      title: "Karacabey Gross Alışveriş Listesi Nasıl Planlanır? | 2026 Güncel Rehber",
      description: "Karacabey gross market haftalık alışveriş listesi nasıl planlanır? Süt, yumurta, tereyağ, zeytin ve temizlik ürünleriyle ekonomik sipariş ipuçları. Ücretsiz teslimat avantajları.",
      keywords: [
        "karacabey gross alışveriş listesi", "haftalık market planı", "karacabey market", "gross sipariş",
        "karacabey online market", "aile alışveriş listesi", "ekonomik market alışverişi", "toplu gıda siparişi",
        "karacabey süt fiyatları", "gross yumurta fiyatları", "karacabey tereyağ", "zeytin kilo fiyatı",
        "temizlik ürünleri toplu", "çamaşır deterjanı fiyatları", "sıvı sabun gross", "market sipariş uygulaması",
        "karacabey ev teslimat", "online market karacabey", "gross market indirim", "haftalık bütçe planlama"
      ],
      ogTitle: "Karacabey Gross Alışveriş Listesi Planlama Rehberi | 2026",
      ogDescription: "Haftalık market listenizi süt, yumurta, tereyağ ve temizlik ürünleriyle optimize edin. Karacabey gross market ücretsiz teslimat avantajları.",
      canonicalUrl: "https://karacabeygross.com/blog/karacabey-gross-alisveris-listesi-nasil-planlanir",
      schemaType: "HowTo",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygross.com" },
        { name: "Blog", url: "https://karacabeygross.com/blog" },
        { name: "Sipariş Planlama", url: "https://karacabeygross.com/blog/kategori/siparis-planlama" },
        { name: "Alışveriş Listesi Planlama", url: "https://karacabeygross.com/blog/karacabey-gross-alisveris-listesi-nasil-planlanir" }
      ]
    },
  },
  {
    slug: "hizli-teslimat-icin-adres-akisi",
    title: "Hızlı teslimat için adres akışını neden önceden düzenlemek gerekir?",
    excerpt: "Adreslerin doğru etiketlenmesi, teslimat notlarının kısa tutulması ve checkout sırasında zaman kaybının azaltılması için öneriler.",
    category: "Teslimat Deneyimi",
    subcategories: ["Adres Yönetimi", "Hızlı Teslimat", "Checkout Optimizasyonu"],
    publishedAt: "2026-04-24",
    readTime: "3 dk",
    heroImage: "https://images.unsplash.com/photo-1520607162513-77705c0f0d4a?auto=format&fit=crop&w=1400&q=80",
    content: [
      "Teslimat akışında en sık yaşanan gecikmelerin önemli bir kısmı yanlış değil, eksik adres bilgisinden doğar. Kat, blok, işletme adı veya giriş talimatı net olmadığında saha ekibi ikinci bir doğrulama yapmak zorunda kalır.",
      "Bu yüzden ev ve iş adreslerini ayrı başlıklarla kaydetmek, her biri için kısa ama tamamlayıcı teslimat notu bırakmak iyi bir alışkanlıktır. Mobilde yapılan her küçük düzenleme, sipariş gününde büyük bir akıcılık sağlar.",
      "Adres akışı sadece lojistik verim için değil, müşteri deneyimi için de kritik bir yüzeydir. Özellikle tekrar sipariş veren kullanıcıda checkout süresi ne kadar kısalırsa sepet tamamlama oranı o kadar yükselir.",
    ],
    products: [
      { name: "Soğuk Zincir Teslimat Paketi", slug: "soguk-zincir-teslimat", category: "Hizmet", priceRange: "0-25 TL", unit: "Hizmet", keywords: ["soğuk zincir", "soğuk teslimat", "gıda teslimat", "karacabey teslimat"] },
      { name: "Hızlı Teslimat Servisi", slug: "hizli-teslimat", category: "Hizmet", priceRange: "0-50 TL", unit: "Hizmet", keywords: ["hızlı teslimat", "express teslimat", "acil market", "karacabey hızlı teslimat"] },
      { name: "Soğutucu Buz Aküsü", slug: "sogutucu-buz-akusu", category: "Aksesuar", priceRange: "50-100 TL", unit: "Adet", keywords: ["buz aküsü", "soğutucu paket", "gıda taşıma", "soğuk zincir aksesuar"] },
      { name: "Yalıtımlı Taşıma Çantası", slug: "yalitimli-tasima-cantasi", category: "Aksesuar", priceRange: "150-300 TL", unit: "Adet", keywords: ["yalıtımlı çanta", "soğutucu çanta", "gıda taşıma çantası", "market çantası"] },
    ],
    seo: {
      title: "Hızlı Teslimat İçin Adres Akışı Düzenleme | Karacabey Gross Market 2026",
      description: "Karacabey'de hızlı market teslimatı için adres akışını nasıl optimize edersiniz? Soğuk zincir teslimat, express servis ve adres yönetimi ipuçları.",
      keywords: [
        "karacabey hızlı teslimat", "market teslimat adresi", "online market teslimat", "karacabey express teslimat",
        "soğuk zincir teslimat", "gıda teslimat hizmeti", "adres yönetimi", "checkout hızlandırma",
        "karacabey market sipariş", "ücretsiz teslimat karacabey", "hızlı market alışverişi", "teslimat notu örnekleri",
        "soğutucu buz aküsü", "yalıtımlı taşıma çantası", "gıda güvenli teslimat", "karacabey evlere servis",
        "market uygulaması adres", "mobil adres kaydetme", "tekrar sipariş hızlandırma", "teslimat optimizasyonu"
      ],
      ogTitle: "Hızlı Teslimat Adres Akışı Rehberi | Karacabey Gross Market",
      ogDescription: "Karacabey'de market siparişlerinizin hızlı teslimatı için adres akışı optimizasyonu. Soğuk zincir ve express servis avantajları.",
      canonicalUrl: "https://karacabeygross.com/blog/hizli-teslimat-icin-adres-akisi",
      schemaType: "FAQPage",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygross.com" },
        { name: "Blog", url: "https://karacabeygross.com/blog" },
        { name: "Teslimat Deneyimi", url: "https://karacabeygross.com/blog/kategori/teslimat-deneyimi" },
        { name: "Adres Akışı Düzenleme", url: "https://karacabeygross.com/blog/hizli-teslimat-icin-adres-akisi" }
      ]
    },
  },
  {
    slug: "online-market-odemede-guven-sinyalleri",
    title: "Online market ödemesinde hangi güven sinyalleri gerçekten önemlidir?",
    excerpt: "SSL, 3D Secure, sağlayıcı doğrulama katmanı ve ödeme sayfası davranışlarını sade bir dille açıklayan rehber.",
    category: "Ödeme Güvenliği",
    subcategories: ["3D Secure", "SSL Sertifikası", "Güvenli Ödeme", "Dijital Güven"],
    publishedAt: "2026-04-20",
    readTime: "5 dk",
    heroImage: "https://images.unsplash.com/photo-1556740749-887f6717d7e4?auto=format&fit=crop&w=1400&q=80",
    content: [
      "Kullanıcı tarafında güven hissi çoğu zaman tek bir ibareye değil, küçük işaretlerin toplamına dayanır. SSL, 3D Secure, bilinen ödeme sağlayıcısı kullanımı ve şeffaf sipariş özeti bu işaretlerin başında gelir.",
      "Profesyonel bir checkout yüzeyi, kullanıcıyı gereksiz bilgiyle yormadan kritik sinyalleri görünür kılar. Toplam tutar, teslimat bilgisi, sepet özeti ve ödeme yönlendirmesi ne kadar açık olursa tereddüt o kadar azalır.",
      "Ödeme deneyimi güven verdiğinde sadece ilk sipariş değil, tekrar sipariş olasılığı da güçlenir. Bu nedenle footer ve checkout yüzeylerindeki güven katmanı doğrudan dönüşüm kalitesiyle ilişkilidir.",
    ],
    products: [
      { name: "Hediye Kartı 100 TL", slug: "hediye-karti-100tl", category: "Kart", priceRange: "100 TL", unit: "Adet", keywords: ["hediye kartı", "market hediye kartı", "karacabey hediye çeki", "100 tl hediye kartı"] },
      { name: "Hediye Kartı 250 TL", slug: "hediye-karti-250tl", category: "Kart", priceRange: "250 TL", unit: "Adet", keywords: ["hediye kartı", "market hediye kartı", "karacabey hediye çeki", "250 tl hediye kartı"] },
      { name: "Kapıda Ödeme Hizmeti", slug: "kapida-odeme", category: "Hizmet", priceRange: "0-15 TL", unit: "Hizmet", keywords: ["kapıda ödeme", "nakit ödeme", "karacabey kapıda ödeme", "güvenli ödeme"] },
      { name: "Taksitli Ödeme Seçeneği", slug: "taksitli-odeme", category: "Hizmet", priceRange: "0 TL", unit: "Hizmet", keywords: ["taksitli ödeme", "kredi kartı taksit", "market taksit", "6 taksit market"] },
    ],
    seo: {
      title: "Online Market Ödeme Güvenliği | SSL, 3D Secure ve Güven Sinyalleri 2026",
      description: "Karacabey gross market ödeme güvenliği rehberi. SSL, 3D Secure, kapıda ödeme ve taksit seçenekleriyle güvenli alışverişin sırları.",
      keywords: [
        "online market ödeme güvenliği", "3D secure ödeme", "SSL sertifikası market", "karacabey güvenli ödeme",
        "kapıda ödeme market", "taksitli alışveriş market", "güvenli online ödeme", "market ödeme yöntemleri",
        "karacabey hediye kartı", "market hediye çeki", "nakit ödeme teslimat", "kredi kartı market alışverişi",
        "ödeme sayfası güvenliği", "dijital güven sinyalleri", "şifreli ödeme", "karacabey online market güvenlik",
        "güvenli checkout", "ödeme sağlayıcısı doğrulama", "market uygulaması ödeme", "güvenli alışveriş ipuçları"
      ],
      ogTitle: "Online Market Ödeme Güvenliği Rehberi | Karacabey Gross Market",
      ogDescription: "SSL, 3D Secure ve güvenli ödeme yöntemleriyle Karacabey gross market'te güvenli alışverişin tüm detayları.",
      canonicalUrl: "https://karacabeygross.com/blog/online-market-odemede-guven-sinyalleri",
      schemaType: "Article",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygross.com" },
        { name: "Blog", url: "https://karacabeygross.com/blog" },
        { name: "Ödeme Güvenliği", url: "https://karacabeygross.com/blog/kategori/odeme-guvenligi" },
        { name: "Güven Sinyalleri", url: "https://karacabeygross.com/blog/online-market-odemede-guven-sinyalleri" }
      ]
    },
  },
  {
    slug: "favori-listesi-ile-tekrar-siparis",
    title: "Favori listesi ile tekrar sipariş süresi nasıl kısalır?",
    excerpt: "Tekrar alınan ürünlerin favorilere alınmasıyla mobilde daha kısa, daha düzenli ve daha tahmin edilebilir sipariş akışı kurulabilir.",
    category: "Mobil Deneyim",
    subcategories: ["Favori Listesi", "Tekrar Sipariş", "Mobil UX", "Hızlı Sepet"],
    publishedAt: "2026-04-17",
    readTime: "4 dk",
    heroImage: "https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1400&q=80",
    content: [
      "Mobil kullanıcılar uzun ürün aramalarından çok hızlı karar akışlarını sever. Bu yüzden tekrar alınan ürünlerin favorilere eklenmesi, yeni sipariş oluştururken ciddi zaman kazandırır.",
      "Favori listesi doğru kullanıldığında kullanıcı önce alışkanlık kalemlerini tamamlar, sonra yeni kampanya ve taze ürünlere yönelir. Böylece ana ihtiyaç listesi her siparişte sıfırdan kurulmaz.",
      "Hazır sepet ve alt app bar gibi yüzeylerle birleşen favori akışı, özellikle tek elde ve kısa sürede alışveriş yapan kullanıcı için daha rahat bir deneyim oluşturur.",
    ],
    products: [
      { name: "Pirinç 5kg", slug: "pirinc-5kg", category: "Bakliyat", priceRange: "200-300 TL", unit: "5 Kilogram", keywords: ["pirinç", "osmancık pirinç", "baldo pirinç", "gross pirinç", "5kg pirinç"] },
      { name: "Mercimek 2.5kg", slug: "mercimek-2_5kg", category: "Bakliyat", priceRange: "120-180 TL", unit: "2.5 Kilogram", keywords: ["mercimek", "yeşil mercimek", "kırmızı mercimek", "gross mercimek"] },
      { name: "Bulgur 5kg", slug: "bulgur-5kg", category: "Bakliyat", priceRange: "150-220 TL", unit: "5 Kilogram", keywords: ["bulgur", "köy bulguru", "gross bulgur", "pilavlık bulgur", "5kg bulgur"] },
      { name: "Ayçiçek Yağı 5lt", slug: "aycicek-yagi-5lt", category: "Yağ ve Sos", priceRange: "300-450 TL", unit: "5 Litre", keywords: ["ayçiçek yağı", "sıvı yağ", "gross yağ", "5 litre yağ", "ekonomik yağ"] },
      { name: "Un 5kg", slug: "un-5kg", category: "Un ve Hamur", priceRange: "100-150 TL", unit: "5 Kilogram", keywords: ["un", "buğday unu", "gross un", "5kg un", "ekmeklik un"] },
      { name: "Şeker 5kg", slug: "seker-5kg", category: "Temel Gıda", priceRange: "150-220 TL", unit: "5 Kilogram", keywords: ["şeker", "toz şeker", "gross şeker", "5kg şeker", "ekonomik şeker"] },
    ],
    seo: {
      title: "Favori Listesi ile Tekrar Sipariş | Hızlı Mobil Market Deneyimi 2026",
      description: "Karacabey gross market'te favori listesi ile pirinç, mercimek, bulgur, yağ ve şeker gibi temel ürünleri tek tıkla tekrar sipariş edin. Mobil hızlı sepet özellikleri.",
      keywords: [
        "favori listesi market", "tekrar sipariş", "mobil market uygulaması", "hızlı sepet karacabey",
        "pirinç 5kg fiyat", "mercimek fiyatları", "bulgur 5kg fiyat", "ayçiçek yağı 5 litre fiyat",
        "gross bakliyat fiyatları", "karacabey pirinç", "karacabey mercimek", "karacabey bulgur fiyatı",
        "un 5kg fiyat", "şeker 5kg fiyat", "temel gıda toplu alım", "aile alışveriş listesi",
        "mobil favori ürünler", "tek tıkla sipariş", "alışkanlık ürünleri", "gross market mobil deneyim",
        "hızlı checkout", "hazır sepet özelliği", "mobil app bar", "tekrar alışveriş optimizasyonu"
      ],
      ogTitle: "Favori Listesi ile Tekrar Sipariş Rehberi | Karacabey Gross Market",
      ogDescription: "Pirinç, mercimek, bulgur ve temel gıdaları favori listenize ekleyerek hızlı tekrar sipariş verin. Karacabey gross market mobil deneyimi.",
      canonicalUrl: "https://karacabeygross.com/blog/favori-listesi-ile-tekrar-siparis",
      schemaType: "HowTo",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygross.com" },
        { name: "Blog", url: "https://karacabeygross.com/blog" },
        { name: "Mobil Deneyim", url: "https://karacabeygross.com/blog/kategori/mobil-deneyim" },
        { name: "Favori Listesi ile Sipariş", url: "https://karacabeygross.com/blog/favori-listesi-ile-tekrar-siparis" }
      ]
    },
  },
  {
    slug: "meyve-sebze-siparisinde-tazelik-nasil-korunur",
    title: "Meyve sebze siparişinde tazelik algısını güçlendiren 5 küçük detay",
    excerpt: "Ürün seçimi, teslimat saatleri ve hızlı tüketim planı ile taze ürün siparişlerini daha kontrollü yönetmek mümkün.",
    category: "Taze Ürünler",
    subcategories: ["Meyve Sebze", "Tazelik Kontrolü", "Saklama Koşulları", "Teslimat Zamanlaması"],
    publishedAt: "2026-04-14",
    readTime: "4 dk",
    heroImage: "https://images.unsplash.com/photo-1542838132-92c53300491e?auto=format&fit=crop&w=1400&q=80",
    content: [
      "Taze ürün siparişlerinde memnuniyet çoğu zaman fiyatla değil, ürünün kapıya ulaştığı andaki diri ve taze görünümüyle ölçülür. Bu yüzden sipariş saati, teslimat aralığı ve saklama planı birlikte düşünülmelidir.",
      "Meyve ve sebze alışverişinde hızlı tüketilecek ürünlerle birkaç gün dayanacak ürünleri ayırmak pratik bir yöntemdir. Böylece kullanıcı sepeti sadece indirim odaklı değil, kullanım sırasına göre de optimize etmiş olur.",
      "Mobil sipariş akışında kategori kartları ve net ürün görselleri, taze ürün tarafında karar süresini ciddi biçimde kısaltır. Aradaki bu küçük UX farkı, tekrar sipariş davranışına doğrudan yansır.",
    ],
    products: [
      { name: "Domates 1kg", slug: "domates-1kg", category: "Sebze", priceRange: "30-50 TL", unit: "1 Kilogram", keywords: ["domates", "salkım domates", "karacabey domates", "taze domates", "gross domates"] },
      { name: "Salatalık 1kg", slug: "salatalik-1kg", category: "Sebze", priceRange: "25-45 TL", unit: "1 Kilogram", keywords: ["salatalık", "kıl salatalık", "karacabey salatalık", "taze salatalık"] },
      { name: "Biber 1kg", slug: "biber-1kg", category: "Sebze", priceRange: "40-80 TL", unit: "1 Kilogram", keywords: ["biber", "dolma biber", "sivri biber", "karacabey biber", "taze biber"] },
      { name: "Elma 1kg", slug: "elma-1kg", category: "Meyve", priceRange: "35-60 TL", unit: "1 Kilogram", keywords: ["elma", "starking elma", "golden elma", "karacabey elma", "taze elma"] },
      { name: "Muz 1kg", slug: "muz-1kg", category: "Meyve", priceRange: "60-100 TL", unit: "1 Kilogram", keywords: ["muz", "ithal muz", "yerli muz", "karacabey muz", "taze muz"] },
      { name: "Portakal 1kg", slug: "portakal-1kg", category: "Meyve", priceRange: "25-50 TL", unit: "1 Kilogram", keywords: ["portakal", "finike portakalı", "karacabey portakal", "taze portakal", "vitamin c"] },
      { name: "Patates 5kg", slug: "patates-5kg", category: "Sebze", priceRange: "80-150 TL", unit: "5 Kilogram", keywords: ["patates", "yeni patates", "karacabey patates", "gross patates", "5kg patates"] },
      { name: "Soğan 5kg", slug: "sogan-5kg", category: "Sebze", priceRange: "60-120 TL", unit: "5 Kilogram", keywords: ["soğan", "kuru soğan", "karacabey soğan", "gross soğan", "5kg soğan"] },
    ],
    seo: {
      title: "Meyve Sebze Siparişinde Tazelik Nasıl Korunur? | Karacabey Gross 2026",
      description: "Karacabey gross market'te domates, salatalık, biber, elma, muz ve patates gibi taze ürünleri en taze haliyle sipariş edin. Tazelik garantisi ve saklama ipuçları.",
      keywords: [
        "meyve sebze siparişi", "taze ürünler karacabey", "online market meyve", "karacabey taze sebze",
        "domates fiyatı 1kg", "salatalık kilo fiyatı", "biber fiyatları", "elma kilo fiyatı",
        "muz fiyatı 1kg", "portakal fiyatları", "patates 5kg fiyat", "soğan 5kg fiyat",
        "karacabey domates", "karacabey patates", "karacabey meyve sebze", "taze ürün teslimatı",
        "sebze sipariş uygulaması", "meyve online sipariş", "tazelik garantisi", "soğuk zincir meyve",
        "gross meyve sebze", "aile meyve sebze paketi", "haftalık sebze siparişi", "taze gıda teslimatı"
      ],
      ogTitle: "Meyve Sebze Tazelik Rehberi | Karacabey Gross Market",
      ogDescription: "Domates, salatalık, biber, elma ve patates gibi taze ürünlerde tazelik garantisi. Karacabey gross market meyve sebze siparişi.",
      canonicalUrl: "https://karacabeygross.com/blog/meyve-sebze-siparisinde-tazelik-nasil-korunur",
      schemaType: "HowTo",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygross.com" },
        { name: "Blog", url: "https://karacabeygross.com/blog" },
        { name: "Taze Ürünler", url: "https://karacabeygross.com/blog/kategori/taze-urunler" },
        { name: "Meyve Sebze Tazeliği", url: "https://karacabeygross.com/blog/meyve-sebze-siparisinde-tazelik-nasil-korunur" }
      ]
    },
  },
  {
    slug: "mobilde-hizli-checkout-icin-3-adim",
    title: "Mobilde daha hızlı checkout için 3 basit düzenleme",
    excerpt: "Adres, sepet ve ödeme akışını sadeleştiren küçük dokunuşlarla mobil sipariş tamamlama süresi ciddi biçimde düşebilir.",
    category: "Mobil Deneyim",
    subcategories: ["Hızlı Checkout", "Mobil UX", "Sepet Optimizasyonu", "Tek Tıkla Ödeme"],
    publishedAt: "2026-04-11",
    readTime: "3 dk",
    heroImage: "https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1400&q=80",
    content: [
      "Mobilde checkout başarısı çoğu zaman hız hissiyle ilgilidir. Kullanıcı tek elde dolaşırken çok fazla alan kaplayan gereksiz bloklar gördüğünde işlem yarıda kalabilir.",
      "Bu yüzden kaydedilmiş adres, sade sepet özeti ve tek dokunuşta görünen satın alma alanı, mobil uygulama hissi veren en önemli üç yüzeydir. Özellikle tekrar siparişte bu üçlü büyük fark yaratır.",
      "Ürün listeleme ekranının iki kolonlu, ürün detay ekranının ise daha odaklı olması; karar ve ödeme arasındaki geçişi hızlandırır. Sonuçta kullanıcı daha az düşünür, daha hızlı tamamlar.",
    ],
    products: [
      { name: "Mobil Uygulama İndirim Kodu", slug: "mobil-indirim-kodu", category: "Kampanya", priceRange: "%5-15", unit: "İndirim", keywords: ["mobil indirim", "app indirim kodu", "karacabey mobil kampanya", "ilk sipariş indirimi"] },
      { name: "Hızlı Sepet Şablonu", slug: "hizli-sepet-sablonu", category: "Özellik", priceRange: "0 TL", unit: "Özellik", keywords: ["hızlı sepet", "sepet şablonu", "mobil sepet", "tek tıkla sepet"] },
      { name: "Bir Tıkla Tekrar Sipariş", slug: "tek-tikla-tekrar-siparis", category: "Özellik", priceRange: "0 TL", unit: "Özellik", keywords: ["tek tıkla sipariş", "tekrar sipariş", "hızlı sipariş", "mobil tekrar al"] },
      { name: "Bildirim Tercihleri Yönetimi", slug: "bildirim-tercihleri", category: "Özellik", priceRange: "0 TL", unit: "Özellik", keywords: ["sipariş bildirimi", "teslimat bildirimi", "mobil bildirim", "push notification"] },
    ],
    seo: {
      title: "Mobilde Hızlı Checkout için 3 Adım | Karacabey Gross Market 2026",
      description: "Karacabey gross market mobil uygulamasında 3 adımda hızlı checkout. Kaydedilmiş adres, sade sepet ve tek tıkla ödeme özellikleriyle hızlı alışveriş.",
      keywords: [
        "mobil checkout hızlandırma", "hızlı ödeme market", "mobil sepet akışı", "karacabey mobil market",
        "tek tıkla ödeme", "kaydedilmiş adres", "sade sepet özeti", "mobil ux market",
        "mobil indirim kodu", "ilk sipariş indirimi", "app indirim", "karacabey mobil kampanya",
        "hızlı sepet şablonu", "tekrar sipariş özelliği", "sipariş bildirimi", "mobil bildirim yönetimi",
        "iki kolonlu ürün listesi", "mobil app bar", "tek el kullanım", "hızlı satın alma butonu",
        "market uygulaması hız", "mobil dönüşüm optimizasyonu", "sepet tamamlama oranı", "mobil kullanıcı deneyimi"
      ],
      ogTitle: "Mobilde Hızlı Checkout Rehberi | Karacabey Gross Market",
      ogDescription: "3 basit adımla mobilde hızlı checkout. Kaydedilmiş adres, sade sepet ve tek tıkla ödeme ile Karacabey gross market'te hızlı alışveriş.",
      canonicalUrl: "https://karacabeygross.com/blog/mobilde-hizli-checkout-icin-3-adim",
      schemaType: "HowTo",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygross.com" },
        { name: "Blog", url: "https://karacabeygross.com/blog" },
        { name: "Mobil Deneyim", url: "https://karacabeygross.com/blog/kategori/mobil-deneyim" },
        { name: "Hızlı Checkout", url: "https://karacabeygross.com/blog/mobilde-hizli-checkout-icin-3-adim" }
      ]
    },
  },
  {
    slug: "gross-alisveriste-aile-butcesi-nasil-korunur",
    title: "Gross alışverişte aile bütçesini korumak için hangi ürünler toplu alınmalı?",
    excerpt: "Toplu alışverişte gerçekten avantaj sağlayan kalemleri ayırmak bütçe kontrolünü kolaylaştırır ve israfı azaltır.",
    category: "Bütçe Yönetimi",
    subcategories: ["Aile Bütçesi", "Toplu Alım", "İsraf Önleme", "Ekonomik Alışveriş"],
    publishedAt: "2026-04-08",
    readTime: "5 dk",
    heroImage: "https://images.unsplash.com/photo-1556740749-887f6717d7e4?auto=format&fit=crop&w=1400&q=80",
    content: [
      "Her ürün toplu alım için uygun değildir. Uzun ömürlü temel gıda, temizlik ve yoğun tüketilen kahvaltılık ürünler bütçe avantajı yaratırken kısa raf ömürlü ürünlerde aynı durum tersine dönebilir.",
      "Aile bütçesi için sağlıklı yöntem, yüksek dönüşlü ürünleri belirleyip bunları kampanya dönemlerinde daha yüksek adetle sepete eklemektir. Böylece depolama ve kullanım dengesi bozulmadan tasarruf elde edilir.",
      "Online markette eski fiyat, yeni fiyat ve birim bilgisi aynı kart üzerinde görünür olduğunda kullanıcı gerçek avantajı daha hızlı fark eder. Bu da kampanya performansını ve sepet büyüklüğünü destekler.",
    ],
    products: [
      { name: "Toz Deterjan 10kg", slug: "toz-deterjan-10kg", category: "Temizlik", priceRange: "400-600 TL", unit: "10 Kilogram", keywords: ["toz deterjan", "çamaşır deterjanı", "gross deterjan", "10kg deterjan", "ekonomik deterjan"] },
      { name: "Bulaşık Deterjanı 5lt", slug: "bulasik-deterjani-5lt", category: "Temizlik", priceRange: "200-350 TL", unit: "5 Litre", keywords: ["bulaşık deterjanı", "sıvı bulaşık", "gross bulaşık", "5lt bulaşık", "ekonomik bulaşık"] },
      { name: "Tuvalet Kağıdı 32'li", slug: "tuvalet-kagidi-32li", category: "Temizlik", priceRange: "300-500 TL", unit: "32 Rulo", keywords: ["tuvalet kağıdı", "gross tuvalet kağıdı", "32li tuvalet kağıdı", "ekonomik kağıt", "karacabey kağıt"] },
      { name: "Kağıt Havlu 12'li", slug: "kagit-havlu-12li", category: "Temizlik", priceRange: "200-350 TL", unit: "12 Rulo", keywords: ["kağıt havlu", "mutfak havlusu", "gross havlu", "12li havlu", "ekonomik havlu"] },
      { name: "Pirinç 10kg", slug: "pirinc-10kg", category: "Bakliyat", priceRange: "400-600 TL", unit: "10 Kilogram", keywords: ["pirinç", "gross pirinç", "10kg pirinç", "ekonomik pirinç", "aile pirinci"] },
      { name: "Ayçiçek Yağı 10lt", slug: "aycicek-yagi-10lt", category: "Yağ ve Sos", priceRange: "550-850 TL", unit: "10 Litre", keywords: ["ayçiçek yağı", "10 litre yağ", "gross yağ", "ekonomik yağ", "aile yağı"] },
      { name: "Makarna 5kg", slug: "makarna-5kg", category: "Makarna ve Erişte", priceRange: "150-250 TL", unit: "5 Kilogram", keywords: ["makarna", "gross makarna", "5kg makarna", "ekonomik makarna", "aile makarnası"] },
      { name: "Çay 1kg", slug: "cay-1kg", category: "Çay ve Kahve", priceRange: "200-400 TL", unit: "1 Kilogram", keywords: ["çay", "siyah çay", "gross çay", "1kg çay", "ekonomik çay", "karacabey çayı"] },
    ],
    seo: {
      title: "Gross Alışverişte Aile Bütçesi Nasıl Korunur? | Toplu Alım Rehberi 2026",
      description: "Karacabey gross market'te deterjan, pirinç, yağ, makarna ve çay gibi ürünleri toplu alarak aile bütçenizi koruyun. Ekonomik alışveriş ipuçları ve fiyat karşılaştırması.",
      keywords: [
        "gross alışveriş bütçe", "aile bütçesi market", "toplu alım avantajları", "karacabey ekonomik alışveriş",
        "toz deterjan 10kg fiyat", "bulaşık deterjanı 5lt fiyat", "tuvalet kağıdı 32li fiyat", "kağıt havlu 12li fiyat",
        "pirinç 10kg fiyat", "ayçiçek yağı 10 litre fiyat", "makarna 5kg fiyat", "çay 1kg fiyat",
        "karacabey deterjan fiyatları", "karacabey pirinç fiyatı", "karacabey yağ fiyatları", "gross temizlik ürünleri",
        "israf önleme", "ekonomik aile alışverişi", "kampanyalı toplu alım", "birim fiyat karşılaştırma",
        "uzun ömürlü stok", "depolama dengesi", "bütçe dostu market", "karacabey gross indirim"
      ],
      ogTitle: "Aile Bütçesi Koruma Rehberi | Karacabey Gross Market",
      ogDescription: "Deterjan, pirinç, yağ ve temel gıdaları toplu alarak bütçenizi koruyun. Karacabey gross market ekonomik alışveriş rehberi.",
      canonicalUrl: "https://karacabeygross.com/blog/gross-alisveriste-aile-butcesi-nasil-korunur",
      schemaType: "Article",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygross.com" },
        { name: "Blog", url: "https://karacabeygross.com/blog" },
        { name: "Bütçe Yönetimi", url: "https://karacabeygross.com/blog/kategori/butce-yonetimi" },
        { name: "Aile Bütçesi Koruma", url: "https://karacabeygross.com/blog/gross-alisveriste-aile-butcesi-nasil-korunur" }
      ]
    },
  },
  {
    slug: "kampanya-donemlerinde-sepeti-nasil-dengelemeli",
    title: "Kampanya dönemlerinde sepeti büyütmeden fırsatlardan nasıl yararlanılır?",
    excerpt: "İndirimli ürünleri gerçekten ihtiyaç listesiyle eşleştirmek, kampanya dönemlerinde kontrolsüz harcamayı önler.",
    category: "Kampanya Yönetimi",
    subcategories: ["İndirim Dönemleri", "Sepet Dengesi", "Fırsat Ürünleri", "Bütçe Kontrolü"],
    publishedAt: "2026-04-05",
    readTime: "4 dk",
    heroImage: "https://images.unsplash.com/photo-1607082348824-0a96f2a4b9da?auto=format&fit=crop&w=1400&q=80",
    content: [
      "Kampanyalar doğru kullanıldığında bütçeyi rahatlatır; plansız kullanıldığında ise gereksiz ürün yükü oluşturur. Bu yüzden sepete ürün eklemeden önce ihtiyaç listesi ile kampanya yüzeyi arasında net bir eşleşme kurulmalıdır.",
      "Sepet içinde toplam tutar, indirim etkisi ve adet kontrolü görünür oldukça kullanıcı daha bilinçli hareket eder. Bu basit şeffaflık, satın alma sonrası pişmanlığı da azaltır.",
      "Mobil kullanıcı için kampanya akışının kısa, ürün detaylarının net ve satın alma butonunun görünür olması dönüşüm kadar memnuniyet için de önemlidir.",
    ],
    products: [
      { name: "Kampanyalı Kahvaltılık Paketi", slug: "kampanyali-kahvaltilik-paketi", category: "Kampanya", priceRange: "150-250 TL", unit: "Paket", keywords: ["kahvaltılık paketi", "kampanyalı kahvaltı", "indirimli kahvaltılık", "karacabey kahvaltı paketi"] },
      { name: "İndirimli Temizlik Seti", slug: "indirimli-temizlik-seti", category: "Kampanya", priceRange: "200-400 TL", unit: "Set", keywords: ["temizlik seti", "kampanyalı temizlik", "indirimli deterjan", "karacabey temizlik paketi"] },
      { name: "Aile Gıda Paketi", slug: "aile-gida-paketi", category: "Kampanya", priceRange: "500-800 TL", unit: "Paket", keywords: ["aile gıda paketi", "kampanyalı gıda", "indirimli bakliyat", "karacabey aile paketi"] },
      { name: "Hafta Sonu Özel İndirim", slug: "hafta-sonu-ozel-indirim", category: "Kampanya", priceRange: "%10-30", unit: "İndirim", keywords: ["hafta sonu indirim", "cumartesi pazar kampanya", "karacabey hafta sonu", "gross indirim"] },
      { name: "İlk Sipariş İndirimi", slug: "ilk-siparis-indirimi", category: "Kampanya", priceRange: "%15-25", unit: "İndirim", keywords: ["ilk sipariş indirim", "yeni müşteri kampanya", "karacabey ilk alışveriş", "hoşgeldin indirimi"] },
      { name: "Sadakat Puanı Sistemi", slug: "sadakat-puani", category: "Kampanya", priceRange: "0 TL", unit: "Puan", keywords: ["sadakat puanı", "market puan", "karacabey puan", "alışveriş puanı", "para puan"] },
    ],
    seo: {
      title: "Kampanya Dönemlerinde Sepet Nasıl Dengelenir? | Karacabey Gross 2026",
      description: "Karacabey gross market kampanya dönemlerinde indirimli kahvaltılık, temizlik ve aile gıda paketleriyle bütçenizi koruyun. Hafta sonu ve ilk sipariş indirimleri.",
      keywords: [
        "kampanya dönemi sepet", "indirimli market alışverişi", "karacabey kampanya", "gross indirim fırsatları",
        "kahvaltılık paketi indirim", "temizlik seti kampanya", "aile gıda paketi", "hafta sonu indirim",
        "ilk sipariş indirimi", "yeni müşteri kampanya", "sadakat puanı", "market puan sistemi",
        "sepet yönetimi", "bütçe kontrolü kampanya", "indirimli ürün seçimi", "fırsat ürünleri",
        "kampanyalı gross ürünler", "karacabey indirim", "online market kampanya", "mobil kampanya takip",
        "indirim kodu market", "kupon kodu karacabey", "sepet indirim hesaplama", "kampanya takvimi"
      ],
      ogTitle: "Kampanya Sepet Dengeleme Rehberi | Karacabey Gross Market",
      ogDescription: "Kampanya dönemlerinde indirimli paketlerle bütçenizi koruyun. Kahvaltılık, temizlik ve aile gıda paketleri Karacabey gross market'te.",
      canonicalUrl: "https://karacabeygross.com/blog/kampanya-donemlerinde-sepeti-nasil-dengelemeli",
      schemaType: "Article",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygross.com" },
        { name: "Blog", url: "https://karacabeygross.com/blog" },
        { name: "Kampanya Yönetimi", url: "https://karacabeygross.com/blog/kategori/kampanya-yonetimi" },
        { name: "Sepet Dengeleme", url: "https://karacabeygross.com/blog/kampanya-donemlerinde-sepeti-nasil-dengelemeli" }
      ]
    },
  },
  // YENİ YAZILAR
  {
    slug: "karacabey-et-ve-sarkuteri-siparisi",
    title: "Karacabey'de et ve şarküteri siparişi nasıl güvenle yapılır?",
    excerpt: "Taze kıyma, kuşbaşı, sucuk ve pastırma gibi şarküteri ürünlerinde soğuk zincir ve hijyen standartlarına dikkat edilmesi gereken noktalar.",
    category: "Taze Ürünler",
    subcategories: ["Et ve Şarküteri", "Soğuk Zincir", "Hijyen Standartları", "Taze Et"],
    publishedAt: "2026-04-02",
    readTime: "5 dk",
    heroImage: "https://images.unsplash.com/photo-1607623814075-e51df1bd6565?auto=format&fit=crop&w=1400&q=80",
    content: [
      "Et ve şarküteri ürünlerinde en kritik unsur soğuk zincirin hiçbir şekilde kırılmamasıdır. Taze kıyma, kuşbaşı ve şarküteri ürünleri +4 derecenin altında taşınmalı ve teslimat anında buzdolabına hemen yerleştirilmelidir.",
      "Karacabey bölgesinde üretim yapan yerel kasap ve şarküteri ürünleri, uzun yolculuk yapmadan tüketiciye ulaştığı için raf ömrü ve tazelik açısından avantaj sağlar. Bu ürünlerde yerel üretim bilgisi ve üretim tarihi etiketi önemlidir.",
      "Online et siparişinde paketleme standartlarına dikkat edilmelidir. Vakumlu paketleme, hijyenik kesim ve uygun gramajlı porsiyonlar hem saklama kolaylığı hem de kullanım pratikliği sağlar.",
    ],
    products: [
      { name: "Dana Kıyma 1kg", slug: "dana-kiyma-1kg", category: "Et ve Şarküteri", priceRange: "250-350 TL", unit: "1 Kilogram", keywords: ["dana kıyma", "taze kıyma", "karacabey kıyma", "gross kıyma", "1kg kıyma"] },
      { name: "Dana Kuşbaşı 1kg", slug: "dana-kusbasi-1kg", category: "Et ve Şarküteri", priceRange: "300-450 TL", unit: "1 Kilogram", keywords: ["dana kuşbaşı", "taze kuşbaşı", "karacabey kuşbaşı", "gross kuşbaşı", "1kg kuşbaşı"] },
      { name: "Sucuk 500gr", slug: "sucuk-500gr", category: "Şarküteri", priceRange: "200-350 TL", unit: "500 Gram", keywords: ["sucuk", "fermente sucuk", "karacabey sucuk", "gross sucuk", "doğal sucuk"] },
      { name: "Pastırma 250gr", slug: "pastirma-250gr", category: "Şarküteri", priceRange: "400-700 TL", unit: "250 Gram", keywords: ["pastırma", "kayseri pastırması", "karacabey pastırma", "gross pastırma", "doğal pastırma"] },
      { name: "Tavuk Göğsü 1kg", slug: "tavuk-gogsu-1kg", category: "Kümes Hayvanları", priceRange: "120-180 TL", unit: "1 Kilogram", keywords: ["tavuk göğsü", "taze tavuk", "karacabey tavuk", "gross tavuk", "1kg tavuk"] },
      { name: "Köfte 500gr", slug: "kofte-500gr", category: "Et ve Şarküteri", priceRange: "200-300 TL", unit: "500 Gram", keywords: ["köfte", "dana köfte", "karacabey köfte", "gross köfte", "hazır köfte"] },
      { name: "Sosis 400gr", slug: "sosis-400gr", category: "Şarküteri", priceRange: "100-180 TL", unit: "400 Gram", keywords: ["sosis", "dana sosis", "karacabey sosis", "gross sosis", "kahvaltılık sosis"] },
      { name: "Sucuklu Yumurta Seti", slug: "sucuklu-yumurta-seti", category: "Kampanya", priceRange: "350-500 TL", unit: "Set", keywords: ["sucuklu yumurta", "kahvaltı seti", "karacabey kahvaltı", "gross kahvaltı seti"] },
    ],
    seo: {
      title: "Karacabey Et ve Şarküteri Siparişi | Soğuk Zincir ve Hijyen Rehberi 2026",
      description: "Karacabey gross market'te dana kıyma, kuşbaşı, sucuk, pastırma ve tavuk göğsü gibi et ürünlerini güvenle sipariş edin. Soğuk zincir garantisi ve yerel üretim.",
      keywords: [
        "karacabey et siparişi", "online et market", "taze kıyma fiyatı", "dana kuşbaşı fiyat",
        "sucuk fiyatları 500gr", "pastırma fiyatı 250gr", "tavuk göğsü 1kg fiyat", "köfte fiyatları",
        "karacabey şarküteri", "yerel et ürünleri", "soğuk zincir et", "hijyenik et paketleme",
        "vakumlu et paketi", "doğal sucuk karacabey", "kayseri pastırması fiyat", "dana sosis fiyat",
        "et ve şarküteri online", "kasap ürünleri market", "taze et teslimatı", "karacabey gross et",
        "kahvaltılık şarküteri seti", "sucuklu yumurta seti", "protein paketi", "aile et paketi"
      ],
      ogTitle: "Et ve Şarküteri Sipariş Rehberi | Karacabey Gross Market",
      ogDescription: "Dana kıyma, kuşbaşı, sucuk ve pastırma gibi et ürünlerinde soğuk zincir garantisi. Karacabey gross market yerel et ürünleri.",
      canonicalUrl: "https://karacabeygross.com/blog/karacabey-et-ve-sarkuteri-siparisi",
      schemaType: "Article",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygross.com" },
        { name: "Blog", url: "https://karacabeygross.com/blog" },
        { name: "Taze Ürünler", url: "https://karacabeygross.com/blog/kategori/taze-urunler" },
        { name: "Et ve Şarküteri", url: "https://karacabeygross.com/blog/karacabey-et-ve-sarkuteri-siparisi" }
      ]
    },
  },
  {
    slug: "bebek-bakim-urunleri-gross-alisveris",
    title: "Bebek bakım ürünlerinde gross alışveriş avantajları nelerdir?",
    excerpt: "Bebek bezi, ıslak mendil, mama ve bebek şampuanı gibi sürekli tüketim ürünlerinde toplu alımın ekonomik ve pratik yönleri.",
    category: "Aile ve Bebek",
    subcategories: ["Bebek Bakımı", "Toplu Alım", "Bebek Bezleri", "Bebek Maması"],
    publishedAt: "2026-03-30",
    readTime: "5 dk",
    heroImage: "https://images.unsplash.com/photo-1519689680058-324335c77eba?auto=format&fit=crop&w=1400&q=80",
    content: [
      "Bebek bakım ürünleri aile bütçesinin önemli bir kısmını oluşturur. Bebek bezi, ıslak mendil, mama ve temizlik ürünleri sürekli ve düzenli tüketim gerektirdiğinden toplu alım ciddi tasarruf sağlar.",
      "Gross alışverişte bebek bezleri beden bazlı paketler halinde sunulduğunda hem stok yönetimi kolaylaşır hem de birim maliyet düşer. Özellikle 3-6 aylık dönemlerde aynı beden kullanımı yoğun olduğundan büyük paketler avantajlıdır.",
      "Bebek maması ve ek gıdalarda son kullanma tarihi kontrolü önemlidir. Güvenilir gross marketlerde ürünlerin rotasyonu düzenli yapıldığından taze stok garantisi daha yüksektir.",
    ],
    products: [
      { name: "Bebek Bezi 5 Numara 60'lı", slug: "bebek-bezi-5-numara-60li", category: "Bebek Bakımı", priceRange: "400-600 TL", unit: "60 Adet", keywords: ["bebek bezi", "5 numara bebek bezi", "gross bebek bezi", "60lı bebek bezi", "ekonomik bebek bezi"] },
      { name: "Islak Mendil 12'li Paket", slug: "islak-mendil-12li", category: "Bebek Bakımı", priceRange: "150-250 TL", unit: "12 Paket", keywords: ["ıslak mendil", "bebek ıslak mendili", "gross ıslak mendil", "12li ıslak mendil", "parabensiz mendil"] },
      { name: "Bebek Maması 800gr", slug: "bebek-mamasi-800gr", category: "Bebek Gıdası", priceRange: "300-500 TL", unit: "800 Gram", keywords: ["bebek maması", "devam maması", "gross mama", "800gr mama", "besleyici mama"] },
      { name: "Bebek Şampuanı 750ml", slug: "bebek-sampuani-750ml", category: "Bebek Bakımı", priceRange: "150-250 TL", unit: "750 Mililitre", keywords: ["bebek şampuanı", "gross bebek şampuanı", "doğal bebek şampuanı", "750ml şampuan", "göz yakmayan şampuan"] },
      { name: "Bebek Losyonu 500ml", slug: "bebek-losyonu-500ml", category: "Bebek Bakımı", priceRange: "120-200 TL", unit: "500 Mililitre", keywords: ["bebek losyonu", "bebek kremi", "gross bebek losyonu", "500ml losyon", "doğal bebek kremi"] },
      { name: "Bebek Pudrası 500gr", slug: "bebek-pudrasi-500gr", category: "Bebek Bakımı", priceRange: "80-150 TL", unit: "500 Gram", keywords: ["bebek pudrası", "gross bebek pudrası", "talk pudra", "500gr pudra", "bebek bakım pudrası"] },
      { name: "Bebek Bezi 4 Numara 80'li", slug: "bebek-bezi-4-numara-80li", category: "Bebek Bakımı", priceRange: "450-650 TL", unit: "80 Adet", keywords: ["bebek bezi", "4 numara bebek bezi", "gross bebek bezi", "80li bebek bezi", "maxi bebek bezi"] },
      { name: "Bebek Bakım Seti", slug: "bebek-bakim-seti", category: "Kampanya", priceRange: "800-1200 TL", unit: "Set", keywords: ["bebek bakım seti", "bebek bakım paketi", "karacabey bebek seti", "gross bebek paketi", "yeni anne seti"] },
    ],
    seo: {
      title: "Bebek Bakım Ürünleri Gross Alışveriş | Karacabey 2026 Fiyat Rehberi",
      description: "Karacabey gross market'te bebek bezi, ıslak mendil, mama ve şampuan gibi bebek bakım ürünlerini toplu alarak tasarruf edin. 2026 güncel fiyatları ve paket avantajları.",
      keywords: [
        "bebek bakım ürünleri", "gross bebek bezi", "karacabey bebek market", "bebek bezi fiyatları 2026",
        "5 numara bebek bezi fiyat", "4 numara bebek bezi fiyat", "80lı bebek bezi", "60lı bebek bezi",
        "ıslak mendil 12li fiyat", "bebek maması 800gr fiyat", "bebek şampuanı 750ml", "bebek losyonu fiyat",
        "bebek pudrası 500gr", "bebek bakım seti", "yeni anne alışverişi", "bebek ihtiyaç listesi",
        "ekonomik bebek ürünleri", "toplu bebek bezi alımı", "bebek bezleri indirim", "karacabey bebek mağazası",
        "online bebek market", "bebek gıdası siparişi", "bebek temizlik ürünleri", "aile bebek paketi"
      ],
      ogTitle: "Bebek Bakım Gross Alışveriş Rehberi | Karacabey Gross Market",
      ogDescription: "Bebek bezi, mama ve bakım ürünlerinde toplu alım avantajları. Karacabey gross market 2026 bebek ürünleri fiyat rehberi.",
      canonicalUrl: "https://karacabeygross.com/blog/bebek-bakim-urunleri-gross-alisveris",
      schemaType: "Article",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygross.com" },
        { name: "Blog", url: "https://karacabeygross.com/blog" },
        { name: "Aile ve Bebek", url: "https://karacabeygross.com/blog/kategori/aile-ve-bebek" },
        { name: "Bebek Bakım Ürünleri", url: "https://karacabeygross.com/blog/bebek-bakim-urunleri-gross-alisveris" }
      ]
    },
  },
  {
    slug: "karacabey-kahvaltilik-urunler-siparis",
    title: "Karacabey kahvaltılık ürünlerinde en çok tercih edilenler hangileri?",
    excerpt: "Zeytin, peynir, bal, reçel ve tahin gibi kahvaltılık ürünlerde yerel üretim ve gross alışveriş avantajları.",
    category: "Kahvaltılık",
    subcategories: ["Kahvaltılık", "Yerel Üretim", "Gross Kahvaltı", "Organik Ürünler"],
    publishedAt: "2026-03-27",
    readTime: "4 dk",
    heroImage: "https://images.unsplash.com/photo-1533089862017-5614ecb352ae?auto=format&fit=crop&w=1400&q=80",
    content: [
      "Karacabey ve çevre köylerde üretilen kahvaltılık ürünler, büyük şehir merkezlerine uzak kalmış doğal lezzetleri sofralara taşır. Özellikle köy peyniri, organik bal ve ev yapımı reçeller bu bölgenin vazgeçilmezlerindendir.",
      "Gross alışverişte kahvaltılık ürünler kilogram bazlı sunulduğunda hem fiyat avantajı sağlar hem de kalabalık aileler için pratik bir çözüm olur. Zeytin, peynir ve bal gibi uzun ömürlü ürünler toplu alım için idealdir.",
      "Kahvaltılık setleri ve paketleri, çeşitlilik arayanlar için uygun fiyatlı alternatifler sunar. Böylece tek tek alınan ürünlerden daha ekonomik bir seçenek elde edilir.",
    ],
    products: [
      { name: "Köy Peyniri 1kg", slug: "koy-peyniri-1kg", category: "Kahvaltılık", priceRange: "250-400 TL", unit: "1 Kilogram", keywords: ["köy peyniri", "beyaz peynir", "karacabey peyniri", "gross peynir", "doğal peynir"] },
      { name: "Organik Bal 1kg", slug: "organik-bal-1kg", category: "Kahvaltılık", priceRange: "400-700 TL", unit: "1 Kilogram", keywords: ["organik bal", "köy balı", "karacabey balı", "gross bal", "doğal bal"] },
      { name: "Zeytin 2kg", slug: "zeytin-2kg", category: "Kahvaltılık", priceRange: "250-400 TL", unit: "2 Kilogram", keywords: ["zeytin", "siyah zeytin", "yeşil zeytin", "karacabey zeytin", "gross zeytin", "2kg zeytin"] },
      { name: "Tahin 1kg", slug: "tahin-1kg", category: "Kahvaltılık", priceRange: "150-250 TL", unit: "1 Kilogram", keywords: ["tahin", "doğal tahin", "karacabey tahin", "gross tahin", "kepeksiz tahin"] },
      { name: "Pekmez 1kg", slug: "pekmez-1kg", category: "Kahvaltılık", priceRange: "120-200 TL", unit: "1 Kilogram", keywords: ["pekmez", "üzüm pekmezi", "karacabey pekmezi", "gross pekmez", "doğal pekmez"] },
      { name: "Reçel Çeşitleri 800gr", slug: "recel-cesitleri-800gr", category: "Kahvaltılık", priceRange: "100-180 TL", unit: "800 Gram", keywords: ["reçel", "ev yapımı reçel", "karacabey reçeli", "gross reçel", "organik reçel"] },
      { name: "Tereyağ 1kg", slug: "tereyag-1kg", category: "Kahvaltılık", priceRange: "350-500 TL", unit: "1 Kilogram", keywords: ["tereyağ", "köy tereyağı", "karacabey tereyağı", "gross tereyağ", "doğal tereyağ"] },
      { name: "Kahvaltılık Mega Paket", slug: "kahvaltilik-mega-paket", category: "Kampanya", priceRange: "1200-1800 TL", unit: "Paket", keywords: ["kahvaltılık paket", "kahvaltı seti", "karacabey kahvaltı", "gross kahvaltı paketi", "aile kahvaltısı"] },
    ],
    seo: {
      title: "Karacabey Kahvaltılık Ürünleri | Peynir, Bal, Zeytin ve Tahin 2026",
      description: "Karacabey gross market'te köy peyniri, organik bal, zeytin, tahin ve pekmez gibi kahvaltılık ürünleri. Yerel üretim ve gross fiyat avantajlarıyla kahvaltı keyfi.",
      keywords: [
        "karacabey kahvaltılık ürünler", "köy peyniri fiyatı", "organik bal fiyatı 1kg", "zeytin 2kg fiyat",
        "tahin 1kg fiyat", "pekmez fiyatları", "ev yapımı reçel", "tereyağ 1kg fiyat",
        "karacabey peyniri", "karacabey balı", "karacabey zeytin", "doğal kahvaltılık",
        "kahvaltılık paket", "kahvaltı seti", "aile kahvaltısı", "organik kahvaltılık",
        "gross kahvaltılık", "online kahvaltı market", "köy ürünleri sipariş", "yerel kahvaltılık",
        "kahvaltılık indirim", "kahvaltı ürünleri toplu", "karacabey kahvaltı kültürü", "doğal beslenme"
      ],
      ogTitle: "Karacabey Kahvaltılık Ürünleri Rehberi | Karacabey Gross Market",
      ogDescription: "Köy peyniri, organik bal, zeytin ve tahin gibi kahvaltılık ürünlerde yerel üretim ve gross fiyat avantajları.",
      canonicalUrl: "https://karacabeygross.com/blog/karacabey-kahvaltilik-urunler-siparis",
      schemaType: "Article",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygross.com" },
        { name: "Blog", url: "https://karacabeygross.com/blog" },
        { name: "Kahvaltılık", url: "https://karacabeygross.com/blog/kategori/kahvaltilik" },
        { name: "Kahvaltılık Ürünler", url: "https://karacabeygross.com/blog/karacabey-kahvaltilik-urunler-siparis" }
      ]
    },
  },
  {
    slug: "gross-market-icecek-siparisi-avantajlari",
    title: "Gross market içecek siparişinde hangi avantajlar öne çıkıyor?",
    excerpt: "Su, meyve suyu, gazlı içecek ve çay gibi ağır ve hacimli içeceklerde kapıya teslimat ve toplu alım kolaylıkları.",
    category: "İçecekler",
    subcategories: ["İçecek", "Toplu İçecek", "Ağır Ürün Teslimatı", "İçecek Kampanyaları"],
    publishedAt: "2026-03-24",
    readTime: "4 dk",
    heroImage: "https://images.unsplash.com/photo-1625772299848-391b6a87d7b3?auto=format&fit=crop&w=1400&q=80",
    content: [
      "İçecek ürünleri market alışverişinin en ağır ve hacimli kalemlerindendir. 19 litrelik damacana su, koli halindeki meyve suları ve gazlı içecekler taşıma zorluğu nedeniyle online sipariş için ideal ürünlerdir.",
      "Gross marketlerde içecekler koli bazlı sunulduğunda birim fiyat avantajı yanında taşıma derdi de ortadan kalkar. Özellikle ofis ve kalabalık aileler için düzenli içecek siparişi büyük kolaylık sağlar.",
      "Mevsimsel kampanyalarda içecek ürünlerinde görülen indirimler, stoklanabilir ürünler olduğundan değerlendirilmelidir. Ancak son kullanma tarihi kontrolü yapılarak israf önlenmelidir.",
    ],
    products: [
      { name: "Damacana Su 19lt", slug: "damacana-su-19lt", category: "İçecek", priceRange: "40-70 TL", unit: "19 Litre", keywords: ["damacana su", "19 litre su", "karacabey su", "gross su", "damacana sipariş"] },
      { name: "Meyve Suyu 1lt x 12", slug: "meyve-suyu-1lt-12li", category: "İçecek", priceRange: "250-400 TL", unit: "12 Litre", keywords: ["meyve suyu", "portakal suyu", "gross meyve suyu", "12li meyve suyu", "koli meyve suyu"] },
      { name: "Gazlı İçecek 2.5lt x 6", slug: "gazli-icecek-2_5lt-6li", category: "İçecek", priceRange: "200-350 TL", unit: "15 Litre", keywords: ["gazlı içecek", "kola", "gross gazlı içecek", "6lı gazlı içecek", "koli içecek"] },
      { name: "Çay 1kg", slug: "cay-1kg", category: "Çay ve Kahve", priceRange: "200-400 TL", unit: "1 Kilogram", keywords: ["çay", "siyah çay", "karacabey çayı", "gross çay", "1kg çay"] },
      { name: "Türk Kahvesi 500gr", slug: "turk-kahvesi-500gr", category: "Çay ve Kahve", priceRange: "250-450 TL", unit: "500 Gram", keywords: ["türk kahvesi", "dibek kahvesi", "karacabey kahvesi", "gross kahve", "500gr kahve"] },
      { name: "Ayran 1lt x 6", slug: "ayran-1lt-6li", category: "İçecek", priceRange: "120-200 TL", unit: "6 Litre", keywords: ["ayran", "gross ayran", "6lı ayran", "koli ayran", "doğal ayran"] },
      { name: "Soda 6'lı Paket", slug: "soda-6li-paket", category: "İçecek", priceRange: "60-100 TL", unit: "6 Adet", keywords: ["soda", "maden suyu", "gross soda", "6lı soda", "doğal soda"] },
      { name: "İçecek Mega Koli", slug: "icecek-mega-koli", category: "Kampanya", priceRange: "500-800 TL", unit: "Koli", keywords: ["içecek koli", "içecek paketi", "karacabey içecek", "gross içecek seti", "aile içecek paketi"] },
    ],
    seo: {
      title: "Gross Market İçecek Siparişi | Su, Meyve Suyu ve Çay Avantajları 2026",
      description: "Karacabey gross market'te damacana su, meyve suyu, gazlı içecek ve çay gibi ağır içecekleri kapınıza teslim ediyoruz. Toplu alım fiyat avantajları ve kampanyalar.",
      keywords: [
        "gross market içecek", "damacana su fiyatı 19lt", "meyve suyu koli fiyat", "gazlı içecek 6lı fiyat",
        "karacabey su siparişi", "online içecek market", "ağır ürün teslimat", "içecek toplu alım",
        "çay 1kg fiyat", "türk kahvesi 500gr fiyat", "ayran 6lı fiyat", "soda 6lı paket fiyat",
        "içecek kampanya", "içecek indirim", "koli içecek fiyatları", "ofis içecek siparişi",
        "aile içecek paketi", "karacabey damacana su", "evlere su servisi", "gross içecek seti",
        "meyve suyu indirim", "gazlı içecek kampanya", "doğal içecekler", "karacabey içecek market"
      ],
      ogTitle: "İçecek Siparişi Avantajları | Karacabey Gross Market",
      ogDescription: "Damacana su, meyve suyu ve çay gibi ağır içeceklerde kapıya teslimat ve toplu alım avantajları. Karacabey gross market.",
      canonicalUrl: "https://karacabeygross.com/blog/gross-market-icecek-siparisi-avantajlari",
      schemaType: "Article",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygross.com" },
        { name: "Blog", url: "https://karacabeygross.com/blog" },
        { name: "İçecekler", url: "https://karacabeygross.com/blog/kategori/icecekler" },
        { name: "İçecek Sipariş Avantajları", url: "https://karacabeygross.com/blog/gross-market-icecek-siparisi-avantajlari" }
      ]
    },
  },
  {
    slug: "kargo-hesaplama-ve-ucretsiz-kargo-rehberi",
    title: "Kargo hesaplama ve ücretsiz kargo kuralları nasıl çalışır?",
    excerpt: "350 TL minimum ödeme, 1500 TL üzeri ücretsiz kargo ve Bursa / Karacabey yerel teslimat istisnasını sade şekilde anlatıyoruz.",
    category: "Kargo Rehberi",
    subcategories: ["Kargo Hesaplama", "Ücretsiz Kargo", "Teslimat"],
    publishedAt: "2026-05-18",
    readTime: "4 dk",
    heroImage: "https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?auto=format&fit=crop&w=1400&q=80",
    content: [
      "Online market siparişlerinde kargo tutarı; sepet tutarı, teslimat bölgesi, kargo firması ve paket desisine göre değişebilir. Bu nedenle checkout öncesinde yaklaşık hesaplama ekranı kullanıcıya netlik sağlar.",
      "Standart kargo akışında minimum ödeme tutarı 350 TL olarak belirlenmiştir. 1500 TL ve üzeri siparişlerde standart kargo ücretsiz uygulanır.",
      "Bursa / Karacabey teslimatlarında ise yerel teslimat akışı devreye girer. Bu durumda standart kargo hesaplama kuralı uygulanmaz ve teslimat checkout sırasında ayrıca değerlendirilir.",
    ],
    products: [],
    seo: {
      title: "Kargo Hesaplama ve Ücretsiz Kargo Rehberi | Karacabey Gross Market",
      description: "Kargo hesaplama, 350 TL minimum ödeme, 1500 TL üzeri ücretsiz kargo ve Bursa Karacabey yerel teslimat kuralları hakkında rehber.",
      keywords: ["kargo hesaplama", "ücretsiz kargo", "1500 TL kargo ücretsiz", "350 TL minimum ödeme", "Karacabey teslimat"],
      ogTitle: "Kargo Hesaplama Rehberi",
      ogDescription: "Karacabey Gross Market kargo ve teslimat kuralları.",
      canonicalUrl: "https://karacabeygrossmarket.com/blog/kargo-hesaplama-ve-ucretsiz-kargo-rehberi",
      schemaType: "Article",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygrossmarket.com" },
        { name: "Blog", url: "https://karacabeygrossmarket.com/blog" },
        { name: "Kargo Rehberi", url: "https://karacabeygrossmarket.com/blog/kargo-hesaplama-ve-ucretsiz-kargo-rehberi" }
      ]
    },
  },
  {
    slug: "aras-yurtici-ptt-dhl-kargo-secimi",
    title: "Aras, Yurtiçi, PTT ve DHL eCommerce arasında nasıl seçim yapılır?",
    excerpt: "Online market gönderilerinde hız, maliyet, takip ve teslimat ağına göre kargo firması seçme rehberi.",
    category: "Kargo Entegrasyonları",
    subcategories: ["Aras Kargo", "Yurtiçi Kargo", "PTT Kargo", "DHL eCommerce"],
    publishedAt: "2026-05-17",
    readTime: "5 dk",
    heroImage: "https://images.unsplash.com/photo-1600880292203-757bb62b4baf?auto=format&fit=crop&w=1400&q=80",
    content: [
      "Kargo firması seçimi sadece fiyatla yapılmamalıdır. Teslimat bölgesi, gönderi desisi, takip kalitesi ve iade operasyonu da toplam deneyimi etkiler.",
      "Yurtiçi ve Aras Kargo standart e-ticaret gönderilerinde sık kullanılan güçlü seçeneklerdir. PTT geniş teslimat ağı ve ekonomik gönderilerde değerlendirilebilir.",
      "DHL eCommerce ise ölçeklenebilir e-ticaret operasyonları ve farklı teslimat senaryoları için planlanabilir. En doğru seçim checkout ve panel verileriyle ölçümlenmelidir.",
    ],
    products: [],
    seo: {
      title: "Aras, Yurtiçi, PTT ve DHL eCommerce Kargo Seçimi | Karacabey Gross Market",
      description: "Aras Kargo, Yurtiçi Kargo, PTT Kargo ve DHL eCommerce seçeneklerini online market gönderileri için karşılaştıran rehber.",
      keywords: ["Aras Kargo", "Yurtiçi Kargo", "PTT Kargo", "DHL eCommerce", "kargo entegrasyonu", "e-ticaret kargo"],
      ogTitle: "Kargo Firması Seçimi Rehberi",
      ogDescription: "Online market gönderileri için kargo firması seçimi.",
      canonicalUrl: "https://karacabeygrossmarket.com/blog/aras-yurtici-ptt-dhl-kargo-secimi",
      schemaType: "Article",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygrossmarket.com" },
        { name: "Blog", url: "https://karacabeygrossmarket.com/blog" },
        { name: "Kargo Entegrasyonları", url: "https://karacabeygrossmarket.com/blog/aras-yurtici-ptt-dhl-kargo-secimi" }
      ]
    },
  },
  {
    slug: "kurumsal-siparis-ve-vergi-bilgileri",
    title: "Kurumsal siparişlerde vergi bilgileri neden doğru alınmalı?",
    excerpt: "Firma ünvanı, vergi dairesi ve vergi numarası alanlarının checkout akışında neden kritik olduğunu anlatıyoruz.",
    category: "Kurumsal Sipariş",
    subcategories: ["Fatura", "Vergi Bilgileri", "Checkout"],
    publishedAt: "2026-05-16",
    readTime: "4 dk",
    heroImage: "https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1400&q=80",
    content: [
      "Kurumsal siparişlerde fatura bilgilerinin checkout sırasında doğru alınması muhasebe ve teslimat operasyonunun sorunsuz ilerlemesini sağlar.",
      "Firma ünvanı, vergi dairesi ve vergi numarası gibi alanlar açık ve sade formlarla toplanmalıdır. Gereksiz metinler kullanıcıyı yorar; kısa ve net alanlar dönüşümü artırır.",
      "Bireysel ve kurumsal sipariş ayrımının erken yapılması, ödeme ve sipariş sonrası belgelerin daha temiz yönetilmesine yardımcı olur.",
    ],
    products: [],
    seo: {
      title: "Kurumsal Sipariş ve Vergi Bilgileri Rehberi | Karacabey Gross Market",
      description: "Kurumsal siparişlerde firma ünvanı, vergi dairesi, vergi numarası ve fatura bilgileri hakkında rehber.",
      keywords: ["kurumsal sipariş", "vergi numarası", "vergi dairesi", "firma ünvanı", "online market fatura"],
      ogTitle: "Kurumsal Sipariş Vergi Bilgileri",
      ogDescription: "Kurumsal sipariş ve fatura alanları için rehber.",
      canonicalUrl: "https://karacabeygrossmarket.com/blog/kurumsal-siparis-ve-vergi-bilgileri",
      schemaType: "Article",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygrossmarket.com" },
        { name: "Blog", url: "https://karacabeygrossmarket.com/blog" },
        { name: "Kurumsal Sipariş", url: "https://karacabeygrossmarket.com/blog/kurumsal-siparis-ve-vergi-bilgileri" }
      ]
    },
  },
  {
    slug: "google-maps-ile-adres-kaydetme",
    title: "Google Maps ile adres kaydetme checkout süresini nasıl kısaltır?",
    excerpt: "Konum alma, açık adres notu ve teslimat doğruluğu için sade adres deneyimi rehberi.",
    category: "Adres Yönetimi",
    subcategories: ["Google Maps", "Konum", "Teslimat"],
    publishedAt: "2026-05-15",
    readTime: "3 dk",
    heroImage: "https://images.unsplash.com/photo-1524661135-423995f22d0b?auto=format&fit=crop&w=1400&q=80",
    content: [
      "Adres kayıt ekranında Google Maps destekli konum alma, özellikle apartman, site veya işletme teslimatlarında doğruluğu artırır.",
      "Konum bilgisi tek başına yeterli değildir; mahalle, bina, kat, daire ve teslimat notu açık şekilde yazılmalıdır.",
      "Mobil kullanıcı için tek dokunuşla konum alma, checkout süresini kısaltır ve tekrar siparişlerde hatayı azaltır.",
    ],
    products: [],
    seo: {
      title: "Google Maps ile Adres Kaydetme | Online Market Teslimat Rehberi",
      description: "Google Maps konum desteği ile adres kaydetme, teslimat doğruluğu ve checkout hızlandırma rehberi.",
      keywords: ["Google Maps adres", "konumla adres kaydetme", "online market teslimat", "adres yönetimi", "checkout adres"],
      ogTitle: "Google Maps Adres Kaydetme Rehberi",
      ogDescription: "Konumla adres kaydetme ve teslimat doğruluğu.",
      canonicalUrl: "https://karacabeygrossmarket.com/blog/google-maps-ile-adres-kaydetme",
      schemaType: "Article",
      breadcrumbs: [
        { name: "Ana Sayfa", url: "https://karacabeygrossmarket.com" },
        { name: "Blog", url: "https://karacabeygrossmarket.com/blog" },
        { name: "Adres Yönetimi", url: "https://karacabeygrossmarket.com/blog/google-maps-ile-adres-kaydetme" }
      ]
    },
  },

];

export function findBlogPost(slug: string) {
  return blogPosts.find((post) => post.slug === slug);
}
