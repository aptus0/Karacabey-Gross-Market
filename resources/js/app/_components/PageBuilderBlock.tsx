import type { ReactNode } from "react";

type PageBuilderBlockProps = {
  eyebrow?: string;
  title: string;
  children: ReactNode;
};

export function PageBuilderBlock({ eyebrow, title, children }: PageBuilderBlockProps) {
  return (
    <section className="page-block">
      {eyebrow ? <p className="eyebrow">{eyebrow}</p> : null}
      <h2>{title}</h2>
      <div className="page-block__body">{children}</div>
    </section>
  );
}
