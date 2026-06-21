import Link from "next/link";

type CampaignBannerProps = {
  title: string;
  description: string;
  href: string;
};

export function CampaignBanner({ title, description, href }: CampaignBannerProps) {
  return (
    <section className="campaign-banner">
      <div>
        <p className="eyebrow">Fırsat</p>
        <h2>{title}</h2>
        <p>{description}</p>
      </div>
      <Link className="secondary-action" href={href}>
        İncele
      </Link>
    </section>
  );
}
