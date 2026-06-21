type SeoHeadProps = {
  data: Record<string, unknown>;
};

export function SeoHead({ data }: SeoHeadProps) {
  return (
    <script
      type="application/ld+json"
      dangerouslySetInnerHTML={{ __html: JSON.stringify(data) }}
    />
  );
}
