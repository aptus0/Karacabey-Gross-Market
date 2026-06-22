import Image from "next/image";

type KgmLogoProps = {
  compact?: boolean;
  variant?: "full" | "app" | "header";
  className?: string;
};

function cx(...values: Array<string | false | null | undefined>) {
  return values.filter(Boolean).join(" ");
}

export function KgmLogo({ compact = false, variant = "full", className }: KgmLogoProps) {
  const boxSize =
    variant === "app"
      ? { width: compact ? 36 : 42, height: compact ? 36 : 42 }
      : { width: compact ? 118 : 154, height: compact ? 38 : 48 };

  const logoClass = cx(
    "kgm-logo",
    "kgm-logo-stable",
    "inline-flex shrink-0 items-center justify-center overflow-hidden",
    variant === "header" && "kgm-logo--header-real",
    variant === "app" && "kgm-logo--app-real",
    compact && "kgm-logo--compact",
    className,
  );

  if (variant === "app") {
    return (
      <span className={logoClass} style={boxSize}>
        <Image
          src="/assets/kgm-favicon-256.png"
          alt="Karacabey Gross Market"
          width={256}
          height={256}
          priority
          sizes={`${boxSize.width}px`}
          style={{ width: "100%", height: "100%", objectFit: "contain" }}
        />
      </span>
    );
  }

  return (
    <span className={logoClass} style={boxSize}>
      <Image
        src="/assets/kgm-logo.png"
        alt="Karacabey Gross Market"
        width={1400}
        height={742}
        priority
        sizes={`${boxSize.width}px`}
        style={{ width: "100%", height: "100%", objectFit: "contain" }}
      />
    </span>
  );
}
