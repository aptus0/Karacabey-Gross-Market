"use client";

import { useEffect } from "react";

const RELOAD_KEY = "kgm_chunk_reload_once";

function isChunkLoadProblem(value: unknown) {
  const message = value instanceof Error
    ? value.message
    : typeof value === "string"
      ? value
      : "";

  return /ChunkLoadError|Loading chunk|_next\/static\/chunks/i.test(message);
}

function reloadOnce() {
  if (typeof window === "undefined") return;

  const lastReload = Number(window.sessionStorage.getItem(RELOAD_KEY) ?? "0");
  if (Date.now() - lastReload < 30_000) return;

  window.sessionStorage.setItem(RELOAD_KEY, String(Date.now()));
  window.location.reload();
}

export function ChunkRecovery() {
  useEffect(() => {
    const handleError = (event: ErrorEvent) => {
      if (isChunkLoadProblem(event.error) || isChunkLoadProblem(event.message)) {
        reloadOnce();
      }
    };

    const handleRejection = (event: PromiseRejectionEvent) => {
      if (isChunkLoadProblem(event.reason)) {
        reloadOnce();
      }
    };

    window.addEventListener("error", handleError);
    window.addEventListener("unhandledrejection", handleRejection);

    return () => {
      window.removeEventListener("error", handleError);
      window.removeEventListener("unhandledrejection", handleRejection);
    };
  }, []);

  return null;
}
