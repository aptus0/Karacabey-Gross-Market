import { Slot } from "@radix-ui/react-slot";
import { cva, type VariantProps } from "class-variance-authority";
import type { ButtonHTMLAttributes } from "react";
import { cn } from "@/lib/utils";

const buttonVariants = cva(
  "inline-flex min-h-10 cursor-pointer items-center justify-center rounded-lg px-4 text-sm font-extrabold transition-colors disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-60",
  {
    variants: {
      variant: {
        primary: "bg-[#FF7A00] text-white hover:bg-[#e86f00] [&_*]:text-white",
        secondary: "border border-[#E4E7EB] bg-white text-[#2B2F36] hover:bg-[#FFF8F0]",
        outline: "border border-[#E4E7EB] bg-transparent text-[#2B2F36] hover:bg-[#FFF8F0]",
        ghost: "bg-transparent text-[#2B2F36] hover:bg-[#FFF8F0]",
      },
    },
    defaultVariants: {
      variant: "primary",
    },
  },
);

type ButtonProps = ButtonHTMLAttributes<HTMLButtonElement> &
  VariantProps<typeof buttonVariants> & {
    asChild?: boolean;
  };

export function Button({ asChild = false, className, variant, ...props }: ButtonProps) {
  const Comp = asChild ? Slot : "button";

  return <Comp className={cn(buttonVariants({ variant }), className)} {...props} />;
}
