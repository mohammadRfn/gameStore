import { useEffect, useRef, useState } from "react";
import type { CSSProperties, ComponentType, ReactNode, SVGProps } from "react";
import { cn } from "../utils/cn";
import { fa } from "../lib/format";
import { Check, ChevronDown } from "./Icons";

/* ---------- Reveal: ظهور نرم هنگام اسکرول ---------- */

export function Reveal({
  children,
  delay = 0,
  className,
}: {
  children: ReactNode;
  delay?: number;
  className?: string;
}) {
  const ref = useRef<HTMLDivElement>(null);
  const [inView, setInView] = useState(false);

  useEffect(() => {
    const el = ref.current;
    if (!el) return;
    const io = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          setInView(true);
          io.disconnect();
        }
      },
      { threshold: 0.1, rootMargin: "0px 0px -6% 0px" }
    );
    io.observe(el);
    return () => io.disconnect();
  }, []);

  return (
    <div
      ref={ref}
      className={cn("reveal", inView && "is-in", className)}
      style={{ "--rd": `${delay}ms` } as CSSProperties}
    >
      {children}
    </div>
  );
}

/* ---------- Card ---------- */

export function Card({ children, className }: { children: ReactNode; className?: string }) {
  return (
    <div
      className={cn(
        "rounded-[20px] border border-line bg-ink-850/80 p-6 shadow-[0_18px_50px_-24px_rgba(0,0,0,0.75)] transition-colors duration-300 hover:border-ink-600",
        className
      )}
    >
      {children}
    </div>
  );
}

/* ---------- Toggle ---------- */

export function Toggle({
  checked,
  onChange,
  accent = "ember",
}: {
  checked: boolean;
  onChange: (v: boolean) => void;
  accent?: "ember" | "jade";
}) {
  return (
    <button
      type="button"
      role="switch"
      aria-checked={checked}
      onClick={() => onChange(!checked)}
      className={cn(
        "relative h-[28px] w-[52px] shrink-0 cursor-pointer rounded-full border transition-all duration-300",
        checked
          ? accent === "ember"
            ? "border-ember-500/70 bg-ember-500 shadow-glow-ember"
            : "border-jade-500/70 bg-jade-500 shadow-glow-jade"
          : "border-line bg-ink-700 hover:border-ink-600"
      )}
    >
      <span
        className={cn(
          "absolute top-1/2 h-[21px] w-[21px] -translate-y-1/2 rounded-full shadow-md transition-all duration-300",
          checked ? "start-[26px] bg-ink-950" : "start-[3px] bg-mist"
        )}
        style={{ transitionTimingFunction: "cubic-bezier(0.34, 1.56, 0.64, 1)" }}
      />
    </button>
  );
}

/* ---------- Slider ---------- */

export function Slider({
  value,
  onChange,
  min,
  max,
  step = 1,
  unit = "",
  color = "var(--color-ember-500)",
  format,
}: {
  value: number;
  onChange: (v: number) => void;
  min: number;
  max: number;
  step?: number;
  unit?: string;
  color?: string;
  format?: (v: number) => string;
}) {
  const p = ((value - min) / (max - min)) * 100;
  return (
    <div className="relative w-full min-w-[180px] pt-8 pb-1">
      <span
        className="absolute top-0 z-10 -translate-x-1/2 whitespace-nowrap rounded-md border border-line bg-ink-800 px-2 py-0.5 font-tech text-[11px] font-medium text-ember-300 shadow-lg"
        style={{ left: `${100 - p}%` }}
      >
        {format ? format(value) : `${fa(value)}${unit}`}
      </span>
      <input
        type="range"
        className="slider"
        min={min}
        max={max}
        step={step}
        value={value}
        onChange={(e) => onChange(Number(e.target.value))}
        style={{ "--p": `${p}%`, "--slider-color": color } as CSSProperties}
      />
    </div>
  );
}

/* ---------- Segmented ---------- */

export function Segmented<T extends string>({
  options,
  value,
  onChange,
  className,
}: {
  options: { value: T; label: ReactNode }[];
  value: T;
  onChange: (v: T) => void;
  className?: string;
}) {
  const n = options.length;
  const i = Math.max(0, options.findIndex((o) => o.value === value));
  return (
    <div
      className={cn("relative grid rounded-xl border border-line bg-ink-900 p-1", className)}
      style={{ gridTemplateColumns: `repeat(${n}, 1fr)` }}
    >
      <span
        aria-hidden
        className="absolute top-1 bottom-1 rounded-lg bg-gradient-to-b from-ember-300 to-ember-500 shadow-glow-ember"
        style={
          {
            width: `calc((100% - 8px) / ${n})`,
            insetInlineStart: `calc(${i} * ((100% - 8px) / ${n}) + 4px)`,
            transition: "inset-inline-start 0.35s cubic-bezier(0.22, 1, 0.36, 1)",
          } as CSSProperties
        }
      />
      {options.map((o) => (
        <button
          key={o.value}
          type="button"
          onClick={() => onChange(o.value)}
          className={cn(
            "relative z-10 flex items-center justify-center gap-1.5 rounded-lg px-3 py-1.5 text-[13px] font-medium transition-colors duration-200",
            o.value === value ? "font-bold text-ink-950" : "text-mist hover:text-fog"
          )}
        >
          {o.label}
        </button>
      ))}
    </div>
  );
}

/* ---------- Select ---------- */

export function Select({
  value,
  onChange,
  options,
  className,
}: {
  value: string;
  onChange: (v: string) => void;
  options: { value: string; label: string }[];
  className?: string;
}) {
  const [open, setOpen] = useState(false);
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    if (!open) return;
    const handler = (e: globalThis.MouseEvent) => {
      if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
    };
    window.addEventListener("mousedown", handler);
    return () => window.removeEventListener("mousedown", handler);
  }, [open]);

  const current = options.find((o) => o.value === value);

  return (
    <div ref={ref} className={cn("relative", className)}>
      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        aria-haspopup="listbox"
        aria-expanded={open}
        className={cn(
          "flex w-full min-w-[170px] cursor-pointer items-center justify-between gap-3 rounded-xl border px-3.5 py-2.5 text-sm transition-all duration-200",
          open
            ? "border-ember-500/60 bg-ink-800 shadow-glow-ember"
            : "border-line bg-ink-800/70 hover:border-ink-600 hover:bg-ink-800"
        )}
      >
        <span className="font-medium">{current?.label}</span>
        <ChevronDown
          className={cn(
            "h-4 w-4 shrink-0 text-mist transition-transform duration-300",
            open && "rotate-180 text-ember-300"
          )}
        />
      </button>
      {open && (
        <div
          role="listbox"
          className="absolute end-0 z-50 mt-2 w-full min-w-[190px] overflow-hidden rounded-xl border border-line bg-ink-850 p-1.5 shadow-2xl shadow-black/60"
          style={{ animation: "kf-drop-in 0.18s cubic-bezier(0.22, 1, 0.36, 1)" }}
        >
          {options.map((o) => (
            <button
              key={o.value}
              type="button"
              role="option"
              aria-selected={o.value === value}
              onClick={() => {
                onChange(o.value);
                setOpen(false);
              }}
              className={cn(
                "flex w-full cursor-pointer items-center justify-between gap-2 rounded-lg px-3 py-2 text-start text-sm transition-colors",
                o.value === value
                  ? "bg-ember-500/12 text-ember-300"
                  : "text-mist hover:bg-ink-700/70 hover:text-fog"
              )}
            >
              <span>{o.label}</span>
              {o.value === value && <Check className="h-4 w-4" />}
            </button>
          ))}
        </div>
      )}
    </div>
  );
}

/* ---------- Row ---------- */

export function Row({
  title,
  desc,
  children,
}: {
  title: string;
  desc?: string;
  children: ReactNode;
}) {
  return (
    <div className="flex items-center justify-between gap-6 border-b border-line/60 py-4 first:pt-1 last:border-0 last:pb-1">
      <div className="min-w-0">
        <p className="font-medium text-fog">{title}</p>
        {desc && <p className="mt-0.5 text-[13px] leading-5 text-mist">{desc}</p>}
      </div>
      <div className="shrink-0">{children}</div>
    </div>
  );
}

/* ---------- SectionHead ---------- */

export function SectionHead({
  icon: Icon,
  title,
  desc,
}: {
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  title: string;
  desc: string;
}) {
  return (
    <div className="mb-5 flex items-start gap-4">
      <span className="grid h-12 w-12 shrink-0 place-items-center rounded-xl border border-ember-500/25 bg-gradient-to-br from-ember-500/20 to-coral-500/10 text-ember-300 shadow-glow-ember transition-transform duration-300 hover:rotate-6 hover:scale-110">
        <Icon className="h-[22px] w-[22px]" />
      </span>
      <div className="pt-0.5">
        <h2 className="font-display text-[26px] leading-9 text-fog">{title}</h2>
        <p className="text-[13px] text-mist">{desc}</p>
      </div>
    </div>
  );
}
