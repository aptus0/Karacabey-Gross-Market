"use client";

import Link from "next/link";
import { useQuery } from "@tanstack/react-query";
import { type FormEvent, useEffect, useMemo, useRef, useState } from "react";
import { PackageSearch, Search } from "lucide-react";
import { Command, CommandItem, CommandList } from "@/app/_components/ui/command";
import { fetchProductSuggestions, localProductSuggestions, type ProductSuggestion } from "@/lib/product-search";
import { productImageUrl } from "@/lib/media";

type SearchBarProps = {
  compact?: boolean;
};

const minSearchLength = 2;
const searchDebounceMs = 180;

export function SearchBar({ compact = false }: SearchBarProps) {
  const [query, setQuery] = useState("");
  const [isOpen, setIsOpen] = useState(false);
  const normalizedQuery = query.trim();
  const [debouncedQuery, setDebouncedQuery] = useState("");
  const fallbackSuggestions = useMemo(() => localProductSuggestions(query), [query]);
  const inputRef = useRef<HTMLInputElement>(null);
  useEffect(() => {
    const timer = window.setTimeout(() => {
      setDebouncedQuery(normalizedQuery);
    }, searchDebounceMs);

    return () => window.clearTimeout(timer);
  }, [normalizedQuery]);

  const suggestionsQuery = useQuery({
    queryKey: ["product-suggestions", debouncedQuery],
    queryFn: ({ signal }) => fetchProductSuggestions(debouncedQuery, signal),
    enabled: debouncedQuery.length >= minSearchLength,
    staleTime: 180_000,
    gcTime: 900_000,
    retry: false,
    placeholderData: (previousData) => previousData,
  });
  const remoteSuggestions = suggestionsQuery.data ?? [];
  const suggestions: ProductSuggestion[] = remoteSuggestions.length > 0 ? remoteSuggestions : fallbackSuggestions;

  function handleSubmit(event: FormEvent<HTMLFormElement>) {
    if (!normalizedQuery) {
      event.preventDefault();
      inputRef.current?.focus();
    }
  }

  function handleQueryChange(value: string) {
    setQuery(value);
    setIsOpen(value.trim().length >= minSearchLength);
  }

  return (
    <form
      className={compact ? "search-bar search-bar--compact" : "search-bar"}
      action="/products"
      onBlur={() => window.setTimeout(() => setIsOpen(false), 200)}
      onFocus={() => setIsOpen(normalizedQuery.length >= minSearchLength)}
      onSubmit={handleSubmit}
    >
      <div className="search-bar__field">
        <Search size={18} />
        <input
          ref={inputRef}
          name="q"
          type="search"
          placeholder="Ürün veya marka ara..."
          aria-label="Ürün ara"
          autoComplete="off"
          value={query}
          maxLength={80}
          onChange={(event) => handleQueryChange(event.target.value)}
        />
      </div>
      {isOpen && suggestions.length > 0 ? (
        <Command className="search-suggestions" shouldFilter={false} loop>
          <div className="search-suggestions__title">Önerilen ürünler</div>
          <CommandList aria-label="Önerilen ürünler">
            {suggestions.map((product) => {
              const imageUrl = productImageUrl(product.image_url);

              return (
                <CommandItem
                  asChild
                  key={`${product.slug}-${product.id ?? product.name}`}
                  value={`${product.name} ${product.brand ?? ""} ${product.category ?? ""}`}
                  onSelect={() => setIsOpen(false)}
                >
                  <Link className="search-suggestion" href={`/product/${encodeURIComponent(product.slug)}`} onClick={() => setIsOpen(false)}>
                    <span className="search-suggestion__media">
                      {imageUrl ? (
                        // eslint-disable-next-line @next/next/no-img-element
                        <img src={imageUrl} alt="" loading="lazy" />
                      ) : (
                        <PackageSearch size={21} />
                      )}
                    </span>
                    <span className="search-suggestion__content">
                      <strong>{product.name}</strong>
                      <small>{[product.brand, product.category].filter(Boolean).join(" • ") || "Karacabey Gross Market"}</small>
                    </span>
                    <span className="search-suggestion__price">{product.price}</span>
                  </Link>
                </CommandItem>
              );
            })}
          </CommandList>
        </Command>
      ) : null}
    </form>
  );
}
