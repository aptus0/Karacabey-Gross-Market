"use client";

import { Command as CommandPrimitive } from "cmdk";
import type { ComponentPropsWithoutRef, ElementRef } from "react";
import { forwardRef } from "react";
import { cn } from "@/lib/utils";

export function Command({ className, ...props }: ComponentPropsWithoutRef<typeof CommandPrimitive>) {
  return <CommandPrimitive className={cn("kgm-command", className)} {...props} />;
}

export const CommandList = forwardRef<
  ElementRef<typeof CommandPrimitive.List>,
  ComponentPropsWithoutRef<typeof CommandPrimitive.List>
>(({ className, ...props }, ref) => <CommandPrimitive.List ref={ref} className={cn("kgm-command__list", className)} {...props} />);
CommandList.displayName = CommandPrimitive.List.displayName;

export const CommandItem = forwardRef<
  ElementRef<typeof CommandPrimitive.Item>,
  ComponentPropsWithoutRef<typeof CommandPrimitive.Item>
>(({ className, ...props }, ref) => <CommandPrimitive.Item ref={ref} className={cn("kgm-command__item", className)} {...props} />);
CommandItem.displayName = CommandPrimitive.Item.displayName;
