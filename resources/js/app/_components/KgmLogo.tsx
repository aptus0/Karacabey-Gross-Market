type KgmLogoProps = {
  compact?: boolean;
  variant?: "full" | "app" | "header";
  className?: string;
};

function cx(...values: Array<string | false | null | undefined>) {
  return values.filter(Boolean).join(" ");
}

export function KgmLogo({ compact = false, variant = "full", className }: KgmLogoProps) {
  const logoClass = cx(
    "kgm-logo",
    variant === "header" && "kgm-logo--header-real",
    variant === "app" && "kgm-logo--app-real",
    compact && "kgm-logo--compact",
    className,
  );

  if (variant === "app") {
    return (
      <span className={logoClass}>
        <img
          src="/assets/kgm-favicon-256.png"
          alt="Karacabey Gross Market"
          width={256}
          height={256}
          loading="eager"
          decoding="async"
        />
      </span>
    );
  }

  return (
    <span className={logoClass}>
      <img
        src="/assets/kgm-logo.png"
        alt="Karacabey Gross Market"
        width={1400}
        height={742}
        loading="eager"
        decoding="async"
      />
    </span>
  );
}
