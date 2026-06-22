"use client";

import * as Dialog from "@radix-ui/react-dialog";
import { X } from "lucide-react";
import * as React from "react";
import { cn } from "@/lib/utils";

const Sheet = Dialog.Root;
const SheetTrigger = Dialog.Trigger;
const SheetClose = Dialog.Close;
const SheetPortal = Dialog.Portal;

const SheetOverlay = React.forwardRef<
  React.ElementRef<typeof Dialog.Overlay>,
  React.ComponentPropsWithoutRef<typeof Dialog.Overlay>
>(({ className, ...props }, ref) => (
  <Dialog.Overlay
    ref={ref}
    className={cn("kgm-sheet-overlay fixed inset-0 z-50 bg-slate-950/45 backdrop-blur-[1px]", className)}
    {...props}
  />
));
SheetOverlay.displayName = Dialog.Overlay.displayName;

const SheetContent = React.forwardRef<
  React.ElementRef<typeof Dialog.Content>,
  React.ComponentPropsWithoutRef<typeof Dialog.Content> & {
    side?: "top" | "right" | "bottom" | "left";
    hideClose?: boolean;
  }
>(({ className, children, side = "right", hideClose = false, ...props }, ref) => (
  <SheetPortal>
    <SheetOverlay />
    <Dialog.Content
      ref={ref}
      aria-describedby={props["aria-describedby"] ?? undefined}
      data-side={side}
      className={cn(
        "kgm-sheet-content fixed z-50 flex flex-col bg-white p-0 shadow-2xl outline-none",
        side === "right" && "inset-y-0 right-0 h-full w-full max-w-[460px]",
        side === "left" && "inset-y-0 left-0 h-full w-full max-w-[460px]",
        side === "bottom" && "inset-x-0 bottom-0 rounded-t-3xl",
        side === "top" && "inset-x-0 top-0 rounded-b-3xl",
        className,
      )}
      {...props}
    >
      {children}
      {!hideClose ? (
        <Dialog.Close className="absolute right-5 top-5 inline-flex h-9 w-9 items-center justify-center rounded-full border border-[#E4E7EB] bg-white text-[#2B2F36] transition hover:bg-[#FFF8F0]">
          <X size={16} />
          <span className="sr-only">Kapat</span>
        </Dialog.Close>
      ) : null}
    </Dialog.Content>
  </SheetPortal>
));
SheetContent.displayName = Dialog.Content.displayName;

const SheetHeader = ({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) => (
  <div className={cn("grid gap-1 border-b border-[#E4E7EB] px-6 py-5 text-left", className)} {...props} />
);

const SheetFooter = ({ className, ...props }: React.HTMLAttributes<HTMLDivElement>) => (
  <div className={cn("mt-auto grid gap-3 border-t border-[#E4E7EB] px-6 py-5", className)} {...props} />
);

const SheetTitle = React.forwardRef<
  React.ElementRef<typeof Dialog.Title>,
  React.ComponentPropsWithoutRef<typeof Dialog.Title>
>(({ className, ...props }, ref) => (
  <Dialog.Title ref={ref} className={cn("text-xl font-black text-[#2B2F36]", className)} {...props} />
));
SheetTitle.displayName = Dialog.Title.displayName;

const SheetDescription = React.forwardRef<
  React.ElementRef<typeof Dialog.Description>,
  React.ComponentPropsWithoutRef<typeof Dialog.Description>
>(({ className, ...props }, ref) => (
  <Dialog.Description
    ref={ref}
    className={cn("text-sm leading-6 text-[#6B7177]", className)}
    {...props}
  />
));
SheetDescription.displayName = Dialog.Description.displayName;

export {
  Sheet,
  SheetClose,
  SheetContent,
  SheetDescription,
  SheetFooter,
  SheetHeader,
  SheetOverlay,
  SheetPortal,
  SheetTitle,
  SheetTrigger,
};
