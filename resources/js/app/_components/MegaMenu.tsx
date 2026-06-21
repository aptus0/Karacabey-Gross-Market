"use client";

import Link from "next/link";
import { useId, useState } from "react";
import { ChevronDown, ChevronRight, Grid3X3 } from "lucide-react";

import type { CategoryMenuItem } from "@/lib/category-menu";

type MegaMenuProps = {
  items: CategoryMenuItem[];
};

export function MegaMenu({ items }: MegaMenuProps) {
  const [isOpen, setIsOpen] = useState(false);
  const panelId = useId();

  return (
    <div
      className={`mega-menu${isOpen ? " is-open" : ""}`}
      onMouseEnter={() => setIsOpen(true)}
      onMouseLeave={() => setIsOpen(false)}
    >
      <button
        className="mega-menu__trigger"
        type="button"
        aria-haspopup="menu"
        aria-expanded={isOpen}
        aria-controls={panelId}
        onClick={() => setIsOpen((open) => !open)}
      >
        Kategoriler
        <ChevronDown size={15} />
      </button>

      <div
        id={panelId}
        className="mega-menu__panel"
        role="menu"
        aria-label="Kategoriler"
      >
        <div className="mega-menu__heading">
          <strong>Reyonlar</strong>
          <span>Ürünleri ana gruplar ve alt koleksiyonlarla keşfedin.</span>
        </div>

        <div className="mega-menu__catalog">
          {items.map((item) => (
            <article key={item.slug} className="mega-menu__card">
              <div className="mega-menu__card-media">
                {item.imageUrl ? (
                  <img src={item.imageUrl} alt={item.name} />
                ) : (
                  <span>
                    <Grid3X3 size={18} />
                  </span>
                )}
              </div>

              <div className="mega-menu__card-body">
                <div className="mega-menu__card-head">
                  <Link
                    href={`/kategori/${item.slug}`}
                    className="mega-menu__card-link"
                    role="menuitem"
                    onClick={() => setIsOpen(false)}
                  >
                    <strong>{item.name}</strong>
                    <ChevronRight size={14} />
                  </Link>

                  {typeof item.count === "number" ? (
                    <span className="mega-menu__count">{item.count} ürün</span>
                  ) : null}
                </div>

                {item.description ? (
                  <p className="mega-menu__card-copy">{item.description}</p>
                ) : null}

                {item.children.length > 0 ? (
                  <div className="mega-menu__subgrid">
                    {item.children.map((child) => (
                      <Link
                        key={child.slug}
                        href={`/kategori/${child.slug}`}
                        className="mega-menu__subitem"
                        role="menuitem"
                        onClick={() => setIsOpen(false)}
                      >
                        <span>{child.name}</span>
                        {child.description ? <small>{child.description}</small> : null}
                      </Link>
                    ))}
                  </div>
                ) : (
                  <Link
                    href={`/kategori/${item.slug}`}
                    className="mega-menu__subitem mega-menu__subitem--single"
                    role="menuitem"
                    onClick={() => setIsOpen(false)}
                  >
                    <span>Bu reyonu aç</span>
                    <small>Ürünleri filtrelenmiş listeyle görüntüle.</small>
                  </Link>
                )}
              </div>
            </article>
          ))}
        </div>
      </div>
    </div>
  );
}
