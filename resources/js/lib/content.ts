export type StorePage = {
  slug: string;
  title: string;
  group: "corporate" | "legal" | "support";
  body: string;
  seo: {
    title: string;
    description: string;
  };
};

export type StoreCampaign = {
  slug: string;
  title: string;
  description: string;
  discount: string;
};

export const storePages: StorePage[] = [
  {
    slug: "hakkimizda",
    title: "Hakkımızda",
    group: "corporate",
    body: "Karacabey Gross Market, Karacabey ve çevresi için hızlı market siparişi, güvenli ödeme ve düzenli teslimat deneyimi sunar.",
    seo: {
      title: "Hakkımızda | Karacabey Gross Market",
      description: "Karacabey Gross Market kurumsal bilgileri, hizmet yaklaşımı ve online market altyapısı.",
    },
  },
  {
    slug: "iletisim",
    title: "İletişim",
    group: "corporate",
    body: "Sipariş, teslimat ve destek talepleriniz için Karacabey Gross Market müşteri hizmetleriyle iletişime geçebilirsiniz.",
    seo: {
      title: "İletişim | Karacabey Gross Market",
      description: "Karacabey Gross Market iletişim ve destek kanalları.",
    },
  },
  {
    slug: "kvkk",
    title: "KVKK",
    group: "legal",
    body: "Kişisel verileriniz, yürürlükteki mevzuata uygun şekilde işlenir, saklanır ve korunur.",
    seo: {
      title: "KVKK | Karacabey Gross Market",
      description: "Karacabey Gross Market KVKK aydınlatma metni.",
    },
  },
  {
    slug: "gizlilik-politikasi",
    title: "Gizlilik Politikası",
    group: "legal",
    body: "Web sitesi, mobil uygulama ve ödeme süreçlerindeki gizlilik yaklaşımımız bu sayfada özetlenir.",
    seo: {
      title: "Gizlilik Politikası | Karacabey Gross Market",
      description: "Karacabey Gross Market gizlilik politikası.",
    },
  },
  {
    slug: "mesafeli-satis-sozlesmesi",
    title: "Mesafeli Satış Sözleşmesi",
    group: "legal",
    body: "Online siparişleriniz mesafeli satış mevzuatı ve ilgili tüketici hakları kapsamında yürütülür.",
    seo: {
      title: "Mesafeli Satış Sözleşmesi | Karacabey Gross Market",
      description: "Karacabey Gross Market mesafeli satış sözleşmesi.",
    },
  },
  {
    slug: "uyelik-sozlesmesi",
    title: "Üyelik Sözleşmesi",
    group: "legal",
    body: "Üyelik hesabı, sosyal giriş bağlama, sipariş geçmişi ve teslimat verilerinin kullanım çerçevesi bu sözleşmede açıklanır.",
    seo: {
      title: "Üyelik Sözleşmesi | Karacabey Gross Market",
      description: "Karacabey Gross Market üyelik sözleşmesi ve hesap kullanım koşulları.",
    },
  },
  {
    slug: "odeme-guvenligi",
    title: "Ödeme Güvenliği",
    group: "legal",
    body: "Ödeme işlemleri SSL, 3D Secure ve sağlayıcı güvenlik katmanlarıyla korunur; kart verileri mağaza uygulamasında düz metin olarak saklanmaz.",
    seo: {
      title: "Ödeme Güvenliği | Karacabey Gross Market",
      description: "Karacabey Gross Market ödeme güvenliği, şifreleme ve doğrulama süreçleri.",
    },
  },
  {
    slug: "iade-ve-degisim",
    title: "İade ve Değişim",
    group: "support",
    body: "İade ve değişim talepleri sipariş detayları, ödeme durumu ve ürün koşullarına göre değerlendirilir.",
    seo: {
      title: "İade ve Değişim | Karacabey Gross Market",
      description: "Karacabey Gross Market iade ve değişim süreçleri.",
    },
  },
  {
    slug: "sss",
    title: "SSS",
    group: "support",
    body: "Teslimat, ödeme, kupon ve hesap işlemleriyle ilgili sık sorulan sorular burada yer alır.",
    seo: {
      title: "SSS | Karacabey Gross Market",
      description: "Karacabey Gross Market sık sorulan sorular.",
    },
  },
  {
    slug: "teslimat-bolgeleri",
    title: "Teslimat Bölgeleri",
    group: "support",
    body: "Karacabey merkez, çevre mahalleler ve kurumsal teslimat noktaları için aktif dağıtım saatleri ve uygunluk bilgileri bu sayfada özetlenir.",
    seo: {
      title: "Teslimat Bölgeleri | Karacabey Gross Market",
      description: "Karacabey Gross Market teslimat bölgeleri ve hizmet kapsamı bilgileri.",
    },
  },
];

export const storeCampaigns: StoreCampaign[] = [
  {
    slug: "haftalik-gross-firsatlari",
    title: "Haftalık Gross Fırsatları",
    description: "Temel gıda ve günlük ürünlerde avantajlı sepetler.",
    discount: "KGM25 kuponuyla 350 TL ve üzeri siparişlerde 25 TL indirim.",
  },
];

export function findStorePage(slug: string) {
  return storePages.find((page) => page.slug === slug);
}
