import { useRef } from "react";
import type { ReactNode, MouseEvent } from "react";
import { cn } from "../utils/cn";

type TiltCardProps = {
  children: ReactNode;
  className?: string;
  max?: number;
};

/** کارت سه‌بعدی با تیلت نرم دنبال‌کننده موس + لایه glare */
export function TiltCard({ children, className, max = 6 }: TiltCardProps) {
  const innerRef = useRef<HTMLDivElement>(null);
  const wrapRef = useRef<HTMLDivElement>(null);

  const onMove = (e: MouseEvent<HTMLDivElement>) => {
    const el = innerRef.current;
    const wrap = wrapRef.current;
    if (!el || !wrap) return;
    const rect = wrap.getBoundingClientRect();
    const px = (e.clientX - rect.left) / rect.width;
    const py = (e.clientY - rect.top) / rect.height;
    el.style.transition = "transform 0.1s ease-out";
    el.style.setProperty("--ry", `${((px - 0.5) * 2 * max).toFixed(2)}deg`);
    el.style.setProperty("--rx", `${(-(py - 0.5) * 2 * max).toFixed(2)}deg`);
    el.style.setProperty("--mx", `${(px * 100).toFixed(1)}%`);
    el.style.setProperty("--my", `${(py * 100).toFixed(1)}%`);
  };

  const onLeave = () => {
    const el = innerRef.current;
    if (!el) return;
    el.style.transition = "transform 0.7s cubic-bezier(0.22, 1, 0.36, 1)";
    el.style.setProperty("--rx", "0deg");
    el.style.setProperty("--ry", "0deg");
  };

  return (
    <div
      ref={wrapRef}
      onMouseMove={onMove}
      onMouseLeave={onLeave}
      className={cn("group/tilt [perspective:950px]", className)}
    >
      <div ref={innerRef} className="tilt-inner relative h-full">
        {children}
        <div className="tilt-glare absolute inset-0 z-20 rounded-[inherit]" />
      </div>
    </div>
  );
}
