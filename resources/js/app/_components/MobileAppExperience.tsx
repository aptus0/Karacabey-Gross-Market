"use client";

import { useState } from "react";
import Link from "next/link";
import { motion, useReducedMotion, type Variants } from "framer-motion";
import {
  Apple,
  ArrowRight,
  Bell,
  CheckCircle2,
  ChevronDown,
  Clock4,
  Heart,
  MapPin,
  Package,
  Search,
  Shield,
  ShoppingBag,
  Smartphone,
  Sparkles,
  Tag,
  Truck,
  Zap,
} from "lucide-react";
import { businessPhone, siteName } from "@/lib/seo";
import { mobileLandingFaq } from "@/lib/mobile-landing";

const features = [
  {
    icon: <Zap size={20} />,
    title: "Şimşek hızında sipariş",
    text: "Sepete ekle, adresi seç, PayTR ile öde. Üç dokunuşta sipariş tamamlanır.",
  },
  {
    icon: <Bell size={20} />,
    title: "Anlık bildirimler",
    text: "Sipariş hazırlanırken, kargoya verilince ve teslim edilince anında haberdar ol.",
  },
  {
    icon: <Tag size={20} />,
    title: "Sadece app'e özel kampanyalar",
    text: "Uygulama kullanıcılarına özel kuponlar ve flash indirimler ilk burada açılır.",
  },
  {
    icon: <Heart size={20} />,
    title: "Favoriler ve hızlı tekrar sipariş",
    text: "Sık aldığın ürünleri tek dokunuşla yeniden sipariş et, favori listeleri oluştur.",
  },
  {
    icon: <MapPin size={20} />,
    title: "Çoklu adres yönetimi",
    text: "Ev, ofis, yazlık — adreslerini kaydet, harita üzerinden konum doğrula.",
  },
  {
    icon: <Truck size={20} />,
    title: "Canlı kargo takibi",
    text: "Kargonun bulunduğu adımı, tahmini teslim saatini ve kuryeyi anlık gör.",
  },
  {
    icon: <Shield size={20} />,
    title: "Face ID ile güvenli giriş",
    text: "Şifre gerekmez; Face ID veya Touch ID ile saniyeler içinde giriş yap.",
  },
  {
    icon: <Sparkles size={20} />,
    title: "Akıllı öneriler",
    text: "Geçmiş siparişlerine göre kişiselleşmiş kategori ve ürün önerileri al.",
  },
];

const steps = [
  {
    icon: <Apple size={18} />,
    title: "App Store'dan indir",
    text: "Ücretsiz, reklamsız. iPhone ve iPad ile uyumlu, iOS 15+.",
  },
  {
    icon: <Smartphone size={18} />,
    title: "Hesabını oluştur",
    text: "Telefon numaranla saniyeler içinde kayıt ol veya mevcut hesabınla giriş yap.",
  },
  {
    icon: <ShoppingBag size={18} />,
    title: "Sepetini hazırla",
    text: "Yüzlerce kategoriden taze ve kuru ürünleri sepete ekle.",
  },
  {
    icon: <Package size={18} />,
    title: "Kapına gelsin",
    text: "Adresi seç, PayTR ile güvenli öde — kapına en hızlı şekilde teslim edilsin.",
  },
];

const stats = [
  { value: "12.000+", label: "Ürün çeşidi" },
  { value: "09:00–21:00", label: "Her gün açığız" },
  { value: "1–3 gün", label: "Standart teslimat" },
  { value: "4.0 / 5", label: "Müşteri memnuniyeti" },
];

const fadeUp: Variants = {
  hidden: { opacity: 0, y: 28 },
  visible: { opacity: 1, y: 0, transition: { duration: 0.6, ease: [0.22, 1, 0.36, 1] } },
};

const staggerContainer: Variants = {
  hidden: {},
  visible: { transition: { staggerChildren: 0.08, delayChildren: 0.1 } },
};

const float: Variants = {
  hidden: { opacity: 0, scale: 0.94 },
  visible: {
    opacity: 1,
    scale: 1,
    transition: { duration: 0.9, ease: [0.22, 1, 0.36, 1] },
  },
};

export function MobileAppExperience() {
  const [email, setEmail] = useState("");
  const [submitted, setSubmitted] = useState(false);
  const prefersReducedMotion = useReducedMotion();

  const floatAnimation = prefersReducedMotion
    ? undefined
    : {
        y: [0, -12, 0],
        transition: { duration: 6, ease: "easeInOut" as const, repeat: Infinity },
      };

  return (
    <main className="mobile-landing min-h-screen bg-white text-[#0F1A2E]">
      {/* HERO */}
      <section className="relative overflow-hidden">
        {/* Decorative orbs */}
        <motion.div
          aria-hidden
          className="pointer-events-none absolute -left-32 -top-32 h-[480px] w-[480px] rounded-full bg-[#FF7A00]/25 blur-[120px]"
          animate={prefersReducedMotion ? undefined : { x: [0, 30, 0], y: [0, 20, 0] }}
          transition={{ duration: 14, repeat: Infinity, ease: "easeInOut" }}
        />
        <motion.div
          aria-hidden
          className="pointer-events-none absolute -right-32 top-20 h-[420px] w-[420px] rounded-full bg-[#2D6DFF]/20 blur-[120px]"
          animate={prefersReducedMotion ? undefined : { x: [0, -25, 0], y: [0, 25, 0] }}
          transition={{ duration: 16, repeat: Infinity, ease: "easeInOut" }}
        />
        <div
          aria-hidden
          className="pointer-events-none absolute inset-0 -z-10 bg-[radial-gradient(circle_at_20%_0%,rgba(255,122,0,0.08),transparent_55%),radial-gradient(circle_at_85%_45%,rgba(45,109,255,0.07),transparent_55%)]"
        />

        <div className="relative mx-auto grid w-full max-w-[1280px] gap-12 px-4 py-16 sm:px-6 lg:grid-cols-[1.05fr_1fr] lg:gap-10 lg:py-24 lg:px-12">
          <motion.div
            className="grid content-center gap-6"
            initial="hidden"
            animate="visible"
            variants={staggerContainer}
          >
            <motion.span
              variants={fadeUp}
              className="inline-flex w-fit items-center gap-2 rounded-full border border-[#FF7A00]/30 bg-[#FFF1E1] px-3.5 py-1.5 text-xs font-black uppercase tracking-[0.18em] text-[#C25400]"
            >
              <Sparkles size={14} /> Yakında App Store'da
            </motion.span>
            <motion.h1
              variants={fadeUp}
              className="text-4xl font-black leading-[1.05] tracking-tight text-[#0F1A2E] sm:text-5xl lg:text-[56px]"
            >
              Karacabey Gross Market <span className="bg-gradient-to-r from-[#FF7A00] to-[#FF4D00] bg-clip-text text-transparent">iPhone'unda</span> cebinde.
            </motion.h1>
            <motion.p
              variants={fadeUp}
              className="max-w-xl text-base leading-7 text-[#5B6B82] sm:text-lg sm:leading-8"
            >
              Tüm market alışverişin parmaklarının ucunda. Ürün arama, kategori filtreleri, favoriler, tekrar sipariş,
              canlı sipariş zaman çizelgesi, Face ID ile güvenli giriş ve sadece uygulamaya özel kampanyalar.
            </motion.p>
            <motion.div variants={fadeUp} className="flex flex-wrap items-center gap-3">
              <motion.a
                whileHover={prefersReducedMotion ? undefined : { y: -3, scale: 1.02 }}
                whileTap={{ scale: 0.97 }}
                href="#beta"
                className="inline-flex items-center gap-2 rounded-2xl bg-black px-5 py-3 text-white shadow-xl ring-1 ring-black/10 transition"
                aria-label="App Store — yakında"
              >
                <Apple size={26} />
                <span className="grid leading-tight">
                  <span className="text-[10px] font-medium uppercase tracking-wide text-white/70">Yakında</span>
                  <strong className="text-base font-black">App Store</strong>
                </span>
              </motion.a>
              <motion.div whileHover={prefersReducedMotion ? undefined : { y: -3 }}>
                <Link
                  href="/products"
                  className="inline-flex items-center gap-2 rounded-2xl border border-[#E4E7EB] bg-white px-5 py-3 text-sm font-black text-[#0F1A2E] shadow-sm transition hover:border-[#FF7A00] hover:shadow-md"
                >
                  Web'den alışverişe başla
                  <ArrowRight size={16} />
                </Link>
              </motion.div>
            </motion.div>
            <motion.dl
              variants={staggerContainer}
              initial="hidden"
              animate="visible"
              className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4"
            >
              {stats.map((stat) => (
                <motion.div
                  key={stat.label}
                  variants={fadeUp}
                  whileHover={prefersReducedMotion ? undefined : { y: -3 }}
                  className="rounded-2xl border border-[#E4E7EB] bg-white px-4 py-3 shadow-sm transition hover:shadow-md"
                >
                  <dt className="text-[10px] font-black uppercase tracking-[0.16em] text-[#94A3B8]">{stat.label}</dt>
                  <dd className="mt-0.5 text-base font-black text-[#0F1A2E] sm:text-lg">{stat.value}</dd>
                </motion.div>
              ))}
            </motion.dl>
          </motion.div>

          {/* iPhone Mockup */}
          <motion.div
            className="relative mx-auto grid place-items-center"
            initial="hidden"
            animate="visible"
            variants={float}
          >
            <motion.div
              aria-hidden
              className="absolute -inset-8 -z-10 rounded-[80px] bg-gradient-to-br from-[#FF7A00]/40 via-[#FFB46B]/20 to-[#2D6DFF]/30 blur-3xl"
              animate={prefersReducedMotion ? undefined : { opacity: [0.6, 0.95, 0.6] }}
              transition={{ duration: 5, repeat: Infinity, ease: "easeInOut" }}
            />
            <motion.div animate={floatAnimation}>
              <IphoneMockup />
            </motion.div>
          </motion.div>
        </div>
      </section>

      {/* FEATURES */}
      <section className="relative border-t border-[#F1F5F9] bg-gradient-to-b from-white to-[#FAFBFD] py-16 lg:py-24">
        <div className="mx-auto w-full max-w-[1280px] px-4 sm:px-6 lg:px-12">
          <motion.div
            initial="hidden"
            whileInView="visible"
            viewport={{ once: true, margin: "-80px" }}
            variants={staggerContainer}
            className="mb-10 grid gap-3 text-center sm:mb-14"
          >
            <motion.span
              variants={fadeUp}
              className="mx-auto inline-flex items-center gap-2 rounded-full bg-[#FFF1E1] px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-[#C25400]"
            >
              Özellikler
            </motion.span>
            <motion.h2
              variants={fadeUp}
              className="text-3xl font-black leading-tight text-[#0F1A2E] sm:text-4xl"
            >
              Cebindeki marketin tüm gücü.
            </motion.h2>
            <motion.p variants={fadeUp} className="mx-auto max-w-2xl text-[#5B6B82]">
              Web'deki her şey, iPhone'a özel optimize edilmiş bir deneyimle. Daha hızlı, daha kişisel, daha güvenli.
            </motion.p>
          </motion.div>

          <motion.div
            initial="hidden"
            whileInView="visible"
            viewport={{ once: true, margin: "-60px" }}
            variants={staggerContainer}
            className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
          >
            {features.map((feature) => (
              <motion.article
                key={feature.title}
                variants={fadeUp}
                whileHover={prefersReducedMotion ? undefined : { y: -6 }}
                className="group grid content-start gap-3 rounded-3xl border border-[#EBEFF4] bg-white p-5 shadow-[0_1px_2px_rgba(15,26,46,0.04)] transition hover:border-[#FF7A00]/40 hover:shadow-[0_20px_40px_-20px_rgba(255,122,0,0.35)]"
              >
                <motion.span
                  whileHover={prefersReducedMotion ? undefined : { rotate: -8, scale: 1.08 }}
                  className="inline-flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-[#FFF1E1] to-[#FFD7AC] text-[#C25400] transition group-hover:from-[#FF7A00] group-hover:to-[#FF4D00] group-hover:text-white"
                >
                  {feature.icon}
                </motion.span>
                <strong className="text-lg font-black text-[#0F1A2E]">{feature.title}</strong>
                <p className="text-sm leading-6 text-[#5B6B82]">{feature.text}</p>
              </motion.article>
            ))}
          </motion.div>
        </div>
      </section>

      {/* STEPS */}
      <section className="relative border-t border-[#F1F5F9] bg-white py-16 lg:py-24">
        <div className="mx-auto w-full max-w-[1280px] px-4 sm:px-6 lg:px-12">
          <motion.div
            initial="hidden"
            whileInView="visible"
            viewport={{ once: true, margin: "-80px" }}
            variants={staggerContainer}
            className="mb-10 grid gap-3 text-center"
          >
            <motion.span
              variants={fadeUp}
              className="mx-auto inline-flex items-center gap-2 rounded-full bg-[#E8F0FF] px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-[#2548A8]"
            >
              Nasıl çalışır?
            </motion.span>
            <motion.h2
              variants={fadeUp}
              className="text-3xl font-black leading-tight text-[#0F1A2E] sm:text-4xl"
            >
              Dört adımda kapına gelsin.
            </motion.h2>
          </motion.div>
          <motion.ol
            initial="hidden"
            whileInView="visible"
            viewport={{ once: true, margin: "-60px" }}
            variants={staggerContainer}
            className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
          >
            {steps.map((step, index) => (
              <motion.li
                key={step.title}
                variants={fadeUp}
                whileHover={prefersReducedMotion ? undefined : { y: -6 }}
                className="relative grid content-start gap-3 rounded-3xl border border-[#EBEFF4] bg-white p-5 shadow-[0_1px_2px_rgba(15,26,46,0.04)] transition hover:shadow-[0_20px_40px_-20px_rgba(45,109,255,0.3)]"
              >
                <span className="absolute right-4 top-4 text-3xl font-black text-[#EBEFF4]">
                  0{index + 1}
                </span>
                <span className="inline-flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-[#FF7A00] to-[#FF4D00] text-white shadow-lg shadow-[#FF7A00]/30">
                  {step.icon}
                </span>
                <strong className="text-lg font-black text-[#0F1A2E]">{step.title}</strong>
                <p className="text-sm leading-6 text-[#5B6B82]">{step.text}</p>
              </motion.li>
            ))}
          </motion.ol>
        </div>
      </section>

      {/* BETA / DOWNLOAD */}
      <section id="beta" className="relative border-t border-[#F1F5F9] bg-gradient-to-b from-[#FAFBFD] to-white py-16 lg:py-24">
        <div className="mx-auto w-full max-w-[1100px] px-4 sm:px-6 lg:px-12">
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, margin: "-80px" }}
            transition={{ duration: 0.7, ease: [0.22, 1, 0.36, 1] }}
            className="relative grid items-center gap-10 overflow-hidden rounded-[36px] border border-[#EBEFF4] bg-white p-6 shadow-[0_24px_60px_-30px_rgba(15,26,46,0.18)] sm:p-10 lg:grid-cols-[1fr_0.9fr] lg:p-14"
          >
            <motion.div
              aria-hidden
              className="pointer-events-none absolute -right-24 -top-24 h-[300px] w-[300px] rounded-full bg-[#FF7A00]/15 blur-3xl"
              animate={prefersReducedMotion ? undefined : { scale: [1, 1.15, 1] }}
              transition={{ duration: 6, repeat: Infinity, ease: "easeInOut" }}
            />
            <div className="relative grid gap-4">
              <span className="inline-flex w-fit items-center gap-2 rounded-full bg-[#FFF1E1] px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-[#C25400]">
                <Clock4 size={13} /> TestFlight Beta
              </span>
              <h2 className="text-3xl font-black leading-tight text-[#0F1A2E] sm:text-4xl">
                Lansman öncesi ilk kullananlardan ol.
              </h2>
              <p className="text-[#5B6B82]">
                E-postanı bırak; TestFlight davetimiz hazır olduğunda sana ilk biz haber verelim. Sıfır spam, sadece
                Karacabey Gross Market lansman bilgilendirmeleri.
              </p>
              <form
                className="flex flex-col gap-3 sm:flex-row"
                onSubmit={(event) => {
                  event.preventDefault();
                  if (!email) return;
                  setSubmitted(true);
                  setEmail("");
                }}
              >
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  placeholder="ornek@karacabeygrossmarket.com"
                  className="h-12 w-full rounded-2xl border border-[#E4E7EB] bg-white px-4 text-sm text-[#0F1A2E] placeholder:text-[#94A3B8] focus:border-[#FF7A00] focus:outline-none focus:ring-2 focus:ring-[#FF7A00]/20"
                />
                <motion.button
                  whileHover={prefersReducedMotion ? undefined : { y: -2 }}
                  whileTap={{ scale: 0.97 }}
                  type="submit"
                  className="inline-flex h-12 items-center justify-center gap-2 rounded-2xl bg-gradient-to-r from-[#FF7A00] to-[#FF4D00] px-6 text-sm font-black text-white shadow-lg shadow-[#FF7A00]/30 transition"
                >
                  Beni Bilgilendir
                  <ArrowRight size={16} />
                </motion.button>
              </form>
              {submitted ? (
                <motion.p
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  className="inline-flex items-center gap-2 text-sm font-semibold text-[#16A34A]"
                >
                  <CheckCircle2 size={16} /> Aboneliğin kaydedildi, lansman bilgisini paylaşacağız.
                </motion.p>
              ) : null}
              <p className="text-xs text-[#94A3B8]">
                Telefonla doğrudan bilgi almak için: <a href={`tel:${businessPhone.replace(/\s+/g, "")}`} className="underline hover:text-[#FF7A00]">{businessPhone}</a>
              </p>
            </div>

            <div className="relative grid gap-3">
              <motion.a
                whileHover={prefersReducedMotion ? undefined : { y: -3, scale: 1.02 }}
                whileTap={{ scale: 0.97 }}
                href="#beta"
                aria-disabled
                className="flex items-center gap-3 rounded-2xl bg-black px-5 py-4 shadow-xl"
              >
                <Apple size={32} className="text-white" />
                <span className="grid leading-tight">
                  <span className="text-[10px] font-medium uppercase tracking-widest text-white/60">Yakında indir</span>
                  <strong className="text-lg font-black text-white">App Store</strong>
                </span>
              </motion.a>
              <div className="flex items-center gap-3 rounded-2xl border border-dashed border-[#E4E7EB] bg-white px-5 py-4 opacity-80">
                <Smartphone size={28} className="text-[#94A3B8]" />
                <span className="grid leading-tight">
                  <span className="text-[10px] font-medium uppercase tracking-widest text-[#94A3B8]">Yol haritasında</span>
                  <strong className="text-sm font-black text-[#5B6B82]">Google Play (Android)</strong>
                </span>
              </div>
              <ul className="grid gap-2 rounded-2xl border border-[#EBEFF4] bg-[#FAFBFD] p-4 text-sm text-[#5B6B82]">
                <li className="inline-flex items-center gap-2"><CheckCircle2 size={14} className="text-[#FF7A00]" /> iOS 15.0+ desteği</li>
                <li className="inline-flex items-center gap-2"><CheckCircle2 size={14} className="text-[#FF7A00]" /> Türkçe, ücretsiz, reklamsız</li>
                <li className="inline-flex items-center gap-2"><CheckCircle2 size={14} className="text-[#FF7A00]" /> Face ID / Touch ID destekli</li>
              </ul>
            </div>
          </motion.div>
        </div>
      </section>

      {/* FAQ */}
      <section className="border-t border-[#F1F5F9] bg-white py-16 lg:py-24">
        <div className="mx-auto w-full max-w-[900px] px-4 sm:px-6 lg:px-12">
          <motion.div
            initial="hidden"
            whileInView="visible"
            viewport={{ once: true, margin: "-80px" }}
            variants={staggerContainer}
            className="mb-10 grid gap-3 text-center"
          >
            <motion.span
              variants={fadeUp}
              className="mx-auto inline-flex items-center gap-2 rounded-full bg-[#FFF1E1] px-3 py-1 text-xs font-black uppercase tracking-[0.18em] text-[#C25400]"
            >
              SSS
            </motion.span>
            <motion.h2
              variants={fadeUp}
              className="text-3xl font-black leading-tight text-[#0F1A2E] sm:text-4xl"
            >
              Mobil uygulama hakkında sıkça sorulanlar
            </motion.h2>
          </motion.div>
          <motion.div
            initial="hidden"
            whileInView="visible"
            viewport={{ once: true, margin: "-60px" }}
            variants={staggerContainer}
            className="grid gap-3"
          >
            {mobileLandingFaq.map((item) => (
              <FaqRow key={item.q} question={item.q} answer={item.a} />
            ))}
          </motion.div>
          <div className="mt-10 grid place-items-center">
            <Link
              href="/sikca-sorulan-sorular"
              className="inline-flex items-center gap-2 rounded-2xl border border-[#E4E7EB] bg-white px-5 py-3 text-sm font-black text-[#0F1A2E] shadow-sm transition hover:border-[#FF7A00] hover:shadow-md"
            >
              Tüm SSS'leri gör
              <ArrowRight size={16} />
            </Link>
          </div>
        </div>
      </section>

      {/* FINAL CTA */}
      <section className="relative overflow-hidden border-t border-[#F1F5F9] bg-gradient-to-b from-white to-[#FAFBFD] py-16 lg:py-20">
        <div className="mx-auto w-full max-w-[1100px] px-4 sm:px-6 lg:px-12">
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true, margin: "-80px" }}
            transition={{ duration: 0.7, ease: [0.22, 1, 0.36, 1] }}
            className="relative grid gap-6 overflow-hidden rounded-[36px] border border-[#EBEFF4] bg-gradient-to-br from-white via-[#FFF8F0] to-[#FFEDD6] p-8 text-center shadow-[0_30px_80px_-40px_rgba(255,122,0,0.4)] sm:p-12"
          >
            <motion.div
              aria-hidden
              className="pointer-events-none absolute -left-20 -top-20 h-[260px] w-[260px] rounded-full bg-[#FF7A00]/25 blur-3xl"
              animate={prefersReducedMotion ? undefined : { x: [0, 30, 0], y: [0, 20, 0] }}
              transition={{ duration: 10, repeat: Infinity, ease: "easeInOut" }}
            />
            <motion.div
              aria-hidden
              className="pointer-events-none absolute -bottom-20 -right-20 h-[260px] w-[260px] rounded-full bg-[#2D6DFF]/20 blur-3xl"
              animate={prefersReducedMotion ? undefined : { x: [0, -25, 0], y: [0, -20, 0] }}
              transition={{ duration: 12, repeat: Infinity, ease: "easeInOut" }}
            />
            <h2 className="relative text-3xl font-black leading-tight text-[#0F1A2E] sm:text-4xl">
              {siteName} cebine sığsın.
            </h2>
            <p className="relative mx-auto max-w-xl text-[#5B6B82]">
              Şu an web'den sipariş ver, mobil uygulama lansmana hazır — TestFlight davetimizi kaçırma.
            </p>
            <div className="relative flex flex-wrap justify-center gap-3">
              <motion.div whileHover={prefersReducedMotion ? undefined : { y: -3 }} whileTap={{ scale: 0.97 }}>
                <Link
                  href="/products"
                  className="inline-flex items-center gap-2 rounded-2xl bg-gradient-to-r from-[#FF7A00] to-[#FF4D00] px-6 py-3 text-sm font-black text-white shadow-lg shadow-[#FF7A00]/30 transition"
                >
                  Hemen alışveriş yap
                  <ArrowRight size={16} />
                </Link>
              </motion.div>
              <motion.a
                whileHover={prefersReducedMotion ? undefined : { y: -3 }}
                whileTap={{ scale: 0.97 }}
                href="#beta"
                className="inline-flex items-center gap-2 rounded-2xl border border-[#E4E7EB] bg-white px-6 py-3 text-sm font-black text-[#0F1A2E] shadow-sm transition hover:border-[#FF7A00] hover:shadow-md"
              >
                <Apple size={16} /> Beta'ya kayıt
              </motion.a>
            </div>
          </motion.div>
        </div>
      </section>
    </main>
  );
}

// ─── iPhone Mockup ─────────────────────────────────────────────────────────
function IphoneMockup() {
  return (
    <div
      className="relative h-[640px] w-[316px] rounded-[56px] border-[10px] border-[#0a0a0a] bg-[#0a0a0a] shadow-[0_50px_100px_-30px_rgba(15,26,46,0.45),0_0_0_2px_rgba(31,41,55,0.6)]"
      role="img"
      aria-label="Karacabey Gross Market iPhone uygulaması önizlemesi"
    >
      <span className="absolute -left-[12px] top-[110px] h-9 w-1 rounded-l bg-[#1f2937]" aria-hidden />
      <span className="absolute -left-[12px] top-[160px] h-16 w-1 rounded-l bg-[#1f2937]" aria-hidden />
      <span className="absolute -left-[12px] top-[230px] h-16 w-1 rounded-l bg-[#1f2937]" aria-hidden />
      <span className="absolute -right-[12px] top-[180px] h-20 w-1 rounded-r bg-[#1f2937]" aria-hidden />

      <div className="relative h-full w-full overflow-hidden rounded-[44px] bg-gradient-to-b from-[#FFF6EC] via-white to-[#F1F5F9]">
        <div className="absolute left-1/2 top-2.5 z-20 h-[26px] w-[100px] -translate-x-1/2 rounded-full bg-black" aria-hidden />

        <div className="relative z-10 flex h-10 items-center justify-between px-6 pt-2 text-[11px] font-black text-[#0F1A2E]">
          <span>09:41</span>
          <span className="flex items-center gap-1">
            <span className="inline-block h-2.5 w-3 rounded-[1px] bg-[#0F1A2E]" />
            <span className="inline-block h-2.5 w-3 rounded-[1px] bg-[#0F1A2E]" />
            <span className="inline-block h-2.5 w-5 rounded-[2px] border border-[#0F1A2E]">
              <span className="block h-full w-3/4 rounded-[1px] bg-[#0F1A2E]" />
            </span>
          </span>
        </div>

        <div className="relative z-10 grid gap-3 px-4 pt-2">
          <div className="flex items-center justify-between">
            <div className="grid leading-tight">
              <span className="text-[9px] font-black uppercase tracking-[0.18em] text-[#94A3B8]">Karacabey</span>
              <strong className="text-[15px] font-black text-[#0F1A2E]">Gross Market</strong>
            </div>
            <motion.span
              className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-gradient-to-br from-[#FF7A00] to-[#FF4D00] text-white shadow-lg shadow-[#FF7A00]/30"
              animate={{ scale: [1, 1.1, 1] }}
              transition={{ duration: 2.2, repeat: Infinity, ease: "easeInOut" }}
            >
              <ShoppingBag size={14} />
            </motion.span>
          </div>

          <div className="flex items-center gap-2 rounded-2xl border border-[#E4E7EB] bg-white px-3 py-2.5 shadow-sm">
            <Search size={14} className="text-[#94A3B8]" />
            <span className="text-[11px] font-semibold text-[#94A3B8]">Ürün, kategori, marka ara…</span>
          </div>

          <motion.div
            className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-[#FF7A00] to-[#FF4D00] p-3 text-white shadow-lg"
            animate={{ boxShadow: [
              "0 10px 25px -10px rgba(255,122,0,0.55)",
              "0 18px 40px -10px rgba(255,77,0,0.7)",
              "0 10px 25px -10px rgba(255,122,0,0.55)",
            ] }}
            transition={{ duration: 3, repeat: Infinity, ease: "easeInOut" }}
          >
            <div className="grid gap-1">
              <span className="text-[9px] font-black uppercase tracking-[0.18em] text-white/80">App'e özel</span>
              <strong className="text-[15px] font-black leading-tight">Bu hafta sepette %20 indirim</strong>
              <span className="inline-flex w-fit items-center gap-1 rounded-full bg-white/20 px-2 py-0.5 text-[9px] font-black">
                <Tag size={9} /> Kupon: KGM20
              </span>
            </div>
            <Sparkles size={42} className="absolute -right-2 -top-1 opacity-30" />
          </motion.div>

          <div className="grid grid-cols-4 gap-2">
            {[
              { label: "Taze", color: "#FF7A00" },
              { label: "İçecek", color: "#2D6DFF" },
              { label: "Şarküteri", color: "#16A34A" },
              { label: "Temizlik", color: "#9333EA" },
            ].map((cat, index) => (
              <motion.div
                key={cat.label}
                className="grid place-items-center gap-1 rounded-2xl border border-[#E4E7EB] bg-white py-2"
                whileHover={{ y: -2 }}
                animate={{ y: [0, -2, 0] }}
                transition={{ duration: 3, delay: index * 0.25, repeat: Infinity, ease: "easeInOut" }}
              >
                <span className="h-7 w-7 rounded-full" style={{ background: cat.color }} />
                <span className="text-[9px] font-black text-[#0F1A2E]">{cat.label}</span>
              </motion.div>
            ))}
          </div>

          <div className="grid grid-cols-2 gap-2">
            {[
              { name: "Süt 1 L", price: "32,90 ₺", emoji: "🥛" },
              { name: "Yumurta 30'lu", price: "129,90 ₺", emoji: "🥚" },
              { name: "Domates kg", price: "44,90 ₺", emoji: "🍅" },
              { name: "Ekmek", price: "12,50 ₺", emoji: "🥖" },
            ].map((product, index) => (
              <motion.div
                key={product.name}
                className="grid gap-1 rounded-2xl border border-[#E4E7EB] bg-white p-2 shadow-sm"
                initial={{ opacity: 0, y: 8 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.45, delay: 0.6 + index * 0.12, ease: [0.22, 1, 0.36, 1] }}
              >
                <div className="grid h-14 place-items-center rounded-xl bg-[#F8FAFC] text-2xl">{product.emoji}</div>
                <strong className="text-[10px] font-black text-[#0F1A2E]">{product.name}</strong>
                <div className="flex items-center justify-between">
                  <span className="text-[11px] font-black text-[#FF7A00]">{product.price}</span>
                  <span className="inline-flex h-5 w-5 items-center justify-center rounded-full bg-[#FF7A00] text-white">
                    <ShoppingBag size={9} />
                  </span>
                </div>
              </motion.div>
            ))}
          </div>
        </div>

        <div className="absolute bottom-0 left-0 right-0 z-10 grid grid-cols-4 gap-1 border-t border-[#E4E7EB] bg-white/95 px-3 py-2 backdrop-blur">
          {[
            { icon: <ShoppingBag size={16} />, label: "Anasayfa", active: true },
            { icon: <Search size={16} />, label: "Ara" },
            { icon: <Heart size={16} />, label: "Favori" },
            { icon: <Package size={16} />, label: "Hesabım" },
          ].map((tab) => (
            <div
              key={tab.label}
              className={`grid place-items-center gap-0.5 rounded-xl py-1 ${tab.active ? "text-[#FF7A00]" : "text-[#94A3B8]"}`}
            >
              {tab.icon}
              <span className="text-[9px] font-black">{tab.label}</span>
            </div>
          ))}
        </div>

        <div className="absolute bottom-1.5 left-1/2 z-20 h-1 w-24 -translate-x-1/2 rounded-full bg-[#0F1A2E]/40" aria-hidden />
      </div>
    </div>
  );
}

// ─── FAQ row ────────────────────────────────────────────────────────────────
function FaqRow({ question, answer }: { question: string; answer: string }) {
  const [open, setOpen] = useState(false);
  return (
    <motion.div
      variants={fadeUp}
      whileHover={{ y: -2 }}
      className="overflow-hidden rounded-2xl border border-[#EBEFF4] bg-white shadow-[0_1px_2px_rgba(15,26,46,0.04)]"
    >
      <button
        type="button"
        className="flex w-full items-center justify-between gap-4 px-5 py-4 text-left"
        aria-expanded={open}
        onClick={() => setOpen((prev) => !prev)}
      >
        <strong className="text-sm font-black text-[#0F1A2E] sm:text-base">{question}</strong>
        <motion.span
          animate={{ rotate: open ? 180 : 0 }}
          transition={{ duration: 0.3, ease: [0.22, 1, 0.36, 1] }}
          className="shrink-0 text-[#94A3B8]"
        >
          <ChevronDown size={16} />
        </motion.span>
      </button>
      <motion.div
        initial={false}
        animate={{ height: open ? "auto" : 0, opacity: open ? 1 : 0 }}
        transition={{ duration: 0.35, ease: [0.22, 1, 0.36, 1] }}
        className="overflow-hidden"
      >
        <p className="border-t border-[#EBEFF4] px-5 py-4 text-sm leading-6 text-[#5B6B82]">{answer}</p>
      </motion.div>
    </motion.div>
  );
}
