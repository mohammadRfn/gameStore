import { useEffect, useRef } from "react";

/** ساخت مسیر چرخ‌دنده به‌صورت رویه‌ای */
function gearPath(teeth: number, R: number, r: number, c: number, hole: number): string {
  const pts: string[] = [];
  const step = (Math.PI * 2) / teeth;
  const P = (rad: number, a: number) =>
    `${(c + Math.cos(a) * rad).toFixed(2)} ${(c + Math.sin(a) * rad).toFixed(2)}`;
  for (let i = 0; i < teeth; i++) {
    const a = i * step;
    pts.push(P(r, a), P(R, a + step * 0.16), P(R, a + step * 0.34), P(r, a + step * 0.5));
  }
  const h = `M ${(c + hole).toFixed(2)} ${c} A ${hole} ${hole} 0 1 0 ${(c - hole).toFixed(2)} ${c} A ${hole} ${hole} 0 1 0 ${(c + hole).toFixed(2)} ${c} Z`;
  return `M${pts.join(" L")} Z ${h}`;
}

function Gear({
  size,
  teeth,
  dur,
  reverse = false,
  hole = 15,
  className,
}: {
  size: number;
  teeth: number;
  dur: number;
  reverse?: boolean;
  hole?: number;
  className?: string;
}) {
  return (
    <svg
      viewBox="0 0 100 100"
      width={size}
      height={size}
      className={className}
      style={{ animation: `${reverse ? "kf-spin-rev" : "kf-spin"} ${dur}s linear infinite` }}
    >
      <path d={gearPath(teeth, 48, 38, 50, hole)} fill="currentColor" fillRule="evenodd" />
      <circle cx="50" cy="50" r="7" fill="currentColor" opacity="0.9" />
    </svg>
  );
}

/** خوشه چرخ‌دنده‌های سه‌بعدی با پارالاکس دنبال‌کننده موس */
export function GearsCluster() {
  const ref = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const onMove = (e: globalThis.MouseEvent) => {
      const el = ref.current;
      if (!el) return;
      const x = (e.clientX / window.innerWidth - 0.5) * 2;
      const y = (e.clientY / window.innerHeight - 0.5) * 2;
      el.style.setProperty("--gx", x.toFixed(3));
      el.style.setProperty("--gy", y.toFixed(3));
    };
    window.addEventListener("mousemove", onMove);
    return () => window.removeEventListener("mousemove", onMove);
  }, []);

  return (
    <div
      ref={ref}
      aria-hidden
      className="relative hidden h-[270px] w-[300px] shrink-0 select-none md:block"
    >
      {/* مدارهای نقطه‌چین چرخان */}
      <div
        className="absolute left-1/2 top-1/2 h-[254px] w-[254px] -translate-x-1/2 -translate-y-1/2 rounded-full border border-dashed border-ink-600/50"
        style={{ animation: "kf-spin 46s linear infinite" }}
      />
      <div
        className="absolute left-1/2 top-1/2 h-[178px] w-[178px] -translate-x-1/2 -translate-y-1/2 rounded-full border border-dashed border-ember-500/20"
        style={{ animation: "kf-spin-rev 30s linear infinite" }}
      />

      {/* لایه‌های عمق — پارالاکس */}
      <div
        className="absolute inset-0 transition-transform duration-300 ease-out will-change-transform"
        style={{ transform: "translate3d(calc(var(--gx, 0) * 17px), calc(var(--gy, 0) * 13px), 0)" }}
      >
        <Gear
          size={168}
          teeth={12}
          dur={26}
          hole={16}
          className="absolute left-[96px] top-[16px] text-ember-400/85 drop-shadow-[0_0_20px_rgba(255,154,31,0.35)]"
        />
      </div>
      <div
        className="absolute inset-0 transition-transform duration-300 ease-out will-change-transform"
        style={{ transform: "translate3d(calc(var(--gx, 0) * 9px), calc(var(--gy, 0) * 7px), 0)" }}
      >
        <Gear
          size={112}
          teeth={10}
          dur={18}
          reverse
          hole={13}
          className="absolute left-[6px] top-[118px] text-ink-600"
        />
      </div>
      <div
        className="absolute inset-0 transition-transform duration-300 ease-out will-change-transform"
        style={{ transform: "translate3d(calc(var(--gx, 0) * -7px), calc(var(--gy, 0) * -6px), 0)" }}
      >
        <Gear
          size={70}
          teeth={8}
          dur={11}
          hole={9}
          className="absolute left-[214px] top-[178px] text-jade-400/80 drop-shadow-[0_0_14px_rgba(63,216,187,0.35)]"
        />
      </div>

      {/* جرقه‌های شناور */}
      <span
        className="absolute left-[44px] top-[32px] h-2 w-2 rounded-full bg-ember-300 shadow-[0_0_12px_rgba(255,207,130,0.9)]"
        style={{ animation: "kf-float 5s ease-in-out infinite" }}
      />
      <span
        className="absolute left-[240px] top-[118px] h-1.5 w-1.5 rounded-full bg-jade-300 shadow-[0_0_10px_rgba(139,238,221,0.9)]"
        style={{ animation: "kf-float 6.5s ease-in-out 1s infinite" }}
      />
      <span
        className="absolute left-[150px] top-[234px] h-1.5 w-1.5 rounded-full bg-ice-300 shadow-[0_0_10px_rgba(168,216,255,0.9)]"
        style={{ animation: "kf-float 7s ease-in-out 0.5s infinite" }}
      />
    </div>
  );
}
