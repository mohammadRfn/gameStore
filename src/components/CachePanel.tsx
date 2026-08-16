import { useEffect, useRef, useState } from "react";
import type { ComponentType, CSSProperties, SVGProps } from "react";
import { cn } from "../utils/cn";
import { fa, fa1, faPct } from "../lib/format";
import { useCountUp } from "../hooks";
import { TiltCard } from "./TiltCard";
import { Row, Select, Slider, Toggle } from "./controls";
import {
  Check,
  Clock,
  Download,
  FileText,
  ImageIcon,
  Info,
  Layers,
  Refresh,
  Trash,
  Zap,
} from "./Icons";

export type ToastKind = "success" | "info" | "danger";
export type PushToast = (kind: ToastKind, msg: string) => void;

type Tint = "ember" | "ice" | "jade" | "coral";

type Category = {
  id: string;
  label: string;
  icon: ComponentType<SVGProps<SVGSVGElement>>;
  tint: Tint;
  value: number;
};

const TINT: Record<Tint, { chip: string; bar: string }> = {
  ember: {
    chip: "border-ember-500/25 bg-ember-500/12 text-ember-300",
    bar: "linear-gradient(90deg, #e97e0a, #ffb44d)",
  },
  ice: {
    chip: "border-ice-400/25 bg-ice-400/12 text-ice-300",
    bar: "linear-gradient(90deg, #3d8fd1, #6cbcff)",
  },
  jade: {
    chip: "border-jade-500/25 bg-jade-500/12 text-jade-300",
    bar: "linear-gradient(90deg, #17bd9d, #3fd8bb)",
  },
  coral: {
    chip: "border-coral-500/25 bg-coral-500/12 text-coral-300",
    bar: "linear-gradient(90deg, #ff5a4e, #ff7a66)",
  },
};

const BURST_COLORS = ["#ffb44d", "#ff7a66", "#3fd8bb", "#ffcf82", "#6cbcff"];
const RING_C = 2 * Math.PI * 88;
const TOTAL = 12;

type Phase = "idle" | "run" | "done";

type Particle = { id: number; dx: number; dy: number; c: string; s: number };

export function CachePanel({
  autoClean,
  setAutoClean,
  cleanInterval,
  setCleanInterval,
  cacheCap,
  setCacheCap,
  keepDownloads,
  setKeepDownloads,
  pushToast,
}: {
  autoClean: boolean;
  setAutoClean: (v: boolean) => void;
  cleanInterval: string;
  setCleanInterval: (v: string) => void;
  cacheCap: number;
  setCacheCap: (v: number) => void;
  keepDownloads: boolean;
  setKeepDownloads: (v: boolean) => void;
  pushToast: PushToast;
}) {
  const [cats, setCats] = useState<Category[]>([
    { id: "dl", label: "کش دانلودها", icon: Download, tint: "ember", value: 3.4 },
    { id: "sh", label: "شیدر و تکسچر", icon: Layers, tint: "ice", value: 2.1 },
    { id: "img", label: "تصاویر و آیکون‌ها", icon: ImageIcon, tint: "jade", value: 1.7 },
    { id: "log", label: "لاگ و داده موقت", icon: FileText, tint: "coral", value: 1.2 },
  ]);
  const [phase, setPhase] = useState<Phase>("idle");
  const [progress, setProgress] = useState(0);
  const [burst, setBurst] = useState<Particle[]>([]);
  const [lastClean, setLastClean] = useState("۳ روز پیش");

  const timerRef = useRef(0);
  const freedRef = useRef(0);

  const used = cats.reduce((s, c) => s + c.value, 0);
  const disp = useCountUp(used, 1100);
  const frac = Math.min(1, Math.max(0, disp / TOTAL));

  useEffect(() => () => window.clearInterval(timerRef.current), []);

  const spawnBurst = () => {
    const parts: Particle[] = Array.from({ length: 20 }, (_, i) => ({
      id: Date.now() + i,
      dx: (Math.random() - 0.5) * 320,
      dy: (Math.random() - 0.62) * 300,
      c: BURST_COLORS[i % BURST_COLORS.length],
      s: 4 + Math.random() * 5,
    }));
    setBurst(parts);
    window.setTimeout(() => setBurst([]), 950);
  };

  const finishClear = () => {
    setCats((cs) =>
      cs.map((c) => ({
        ...c,
        value: c.id === "dl" ? 0.15 : c.id === "sh" ? 0.08 : c.id === "img" ? 0.12 : 0.05,
      }))
    );
    spawnBurst();
    pushToast("success", `${fa1(freedRef.current)} گیگابایت فضا آزاد شد`);
    setLastClean("همین حالا");
    setPhase("done");
    window.setTimeout(() => {
      setPhase("idle");
      setProgress(0);
    }, 1700);
  };

  const startClear = () => {
    if (phase === "run") return;
    freedRef.current = used;
    setPhase("run");
    setProgress(0);
    timerRef.current = window.setInterval(() => {
      setProgress((p) => Math.min(100, p + 1.6 + Math.random() * 3.4));
    }, 42);
  };

  /* تکمیل پاک‌سازی وقتی پیشرفت به ۱۰۰ رسید */
  useEffect(() => {
    if (phase === "run" && progress >= 100) {
      window.clearInterval(timerRef.current);
      finishClear();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [phase, progress]);

  const quickClean = () => {
    if (phase === "run") return;
    const logCat = cats.find((c) => c.id === "log");
    const freed = logCat ? Math.max(0, logCat.value - 0.05) : 0;
    setCats((cs) => cs.map((c) => (c.id === "log" ? { ...c, value: 0.05 } : c)));
    pushToast("info", `فایل‌های موقت پاک شد — ${fa1(freed)} گیگابایت`);
    setLastClean("همین حالا");
  };

  return (
    <TiltCard className="corner-cut">
      <div className="relative overflow-hidden rounded-[20px] border border-ember-500/20 bg-ink-850/85 p-6 md:p-8">
        {/* دکور پس‌زمینه */}
        <div
          className="pointer-events-none absolute -left-24 -top-24 h-72 w-72 rounded-full bg-ember-500/10 blur-3xl"
          aria-hidden
        />
        <div
          className="pointer-events-none absolute inset-0"
          aria-hidden
          style={{
            backgroundImage:
              "repeating-linear-gradient(45deg, rgba(255,255,255,0.014) 0 2px, transparent 2px 16px)",
          }}
        />

        {/* سربرگ */}
        <div
          className="relative flex flex-wrap items-start justify-between gap-3"
          style={{ transform: "translateZ(24px)" }}
        >
          <div>
            <h3 className="font-display text-3xl leading-10 text-fog">مرکز فرمان کش</h3>
            <p className="mt-1 text-[13px] text-mist">
              مصرف فضا را زنده ببین و با یک کلیک آزادش کن
            </p>
          </div>
          <span className="flex items-center gap-2 rounded-full border border-jade-500/30 bg-jade-500/10 px-3 py-1.5 font-tech text-[10px] tracking-widest text-jade-300">
            <span
              className="h-1.5 w-1.5 rounded-full bg-jade-400"
              style={{ animation: "kf-blink 1.8s ease-in-out infinite" }}
            />
            LIVE MONITOR
          </span>
        </div>

        {/* حلقه + تفکیک کش */}
        <div className="relative mt-8 grid items-center gap-9 md:grid-cols-[auto_1fr]">
          <div
            className="relative mx-auto h-[220px] w-[220px] shrink-0"
            style={{ transform: "translateZ(30px)" }}
          >
            <svg viewBox="0 0 220 220" className="h-full w-full -rotate-90">
              <defs>
                <linearGradient id="cacheGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                  <stop offset="0%" stopColor="#ffb44d" />
                  <stop offset="55%" stopColor="#ff9a1f" />
                  <stop offset="100%" stopColor="#ff5a4e" />
                </linearGradient>
              </defs>
              <circle cx="110" cy="110" r="88" fill="none" stroke="#1a2540" strokeWidth="15" />
              <circle
                cx="110"
                cy="110"
                r="88"
                fill="none"
                stroke="url(#cacheGrad)"
                strokeWidth="15"
                strokeLinecap="round"
                strokeDasharray={RING_C}
                strokeDashoffset={RING_C * (1 - frac)}
                style={{ filter: "drop-shadow(0 0 10px rgba(255,154,31,0.45))" }}
              />
            </svg>
            <div className="absolute inset-0 grid place-items-center">
              <div className="text-center">
                <p className="font-display text-[42px] leading-[46px] text-ember-300">
                  {faPct(frac * 100)}
                </p>
                <p className="mt-1 text-[12.5px] text-mist">
                  {fa1(disp)} از {fa(TOTAL)} گیگابایت
                </p>
              </div>
            </div>
          </div>

          <div className="space-y-3.5">
            {cats.map((c, i) => (
              <div
                key={c.id}
                className="flex items-center gap-3.5 rounded-xl px-2 py-1.5 transition-colors duration-200 hover:bg-ink-800/70"
              >
                <span
                  className={cn(
                    "grid h-10 w-10 shrink-0 place-items-center rounded-lg border",
                    TINT[c.tint].chip
                  )}
                >
                  <c.icon className="h-[18px] w-[18px]" />
                </span>
                <div className="min-w-0 flex-1">
                  <div className="flex items-baseline justify-between gap-2">
                    <p className="text-[13.5px] font-medium text-fog">{c.label}</p>
                    <p className="font-tech text-[12.5px] text-mist">
                      {fa1(c.value)} <span className="text-dim">GB</span>
                    </p>
                  </div>
                  <div className="mt-1.5 h-[7px] overflow-hidden rounded-full bg-ink-700">
                    <div
                      className="h-full rounded-full transition-[width] duration-1000 ease-out"
                      style={{
                        width: `${Math.min(100, (c.value / 4) * 100)}%`,
                        background: TINT[c.tint].bar,
                        transitionDelay: `${i * 90}ms`,
                      }}
                    />
                  </div>
                </div>
              </div>
            ))}
            <p className="flex items-center gap-2 px-2 pt-1 text-[12px] text-dim">
              <Clock className="h-3.5 w-3.5" />
              آخرین پاک‌سازی: {lastClean}
            </p>
          </div>
        </div>

        {/* دکمه‌های پاک‌سازی */}
        <div className="relative mt-8 flex flex-col gap-3 sm:flex-row">
          <div className="relative flex-1">
            <button
              type="button"
              onClick={startClear}
              disabled={phase === "run"}
              className={cn(
                "shine-btn relative w-full cursor-pointer overflow-hidden rounded-xl px-5 py-3.5 font-bold transition-all duration-300 active:scale-[0.97]",
                phase === "done"
                  ? "bg-jade-500 text-ink-950"
                  : "bg-gradient-to-l from-coral-500 to-ember-500 text-[#210d04] hover:brightness-110 hover:shadow-glow-ember",
                phase === "run" && "cursor-wait"
              )}
            >
              {phase === "run" && (
                <span
                  className="absolute inset-y-0 start-0 bg-ink-950/50"
                  style={{ width: `${100 - progress}%` }}
                />
              )}
              <span className="relative flex items-center justify-center gap-2.5">
                {phase === "idle" && (
                  <>
                    <Trash className="h-5 w-5" />
                    پاک‌سازی کامل کش
                  </>
                )}
                {phase === "run" && (
                  <>
                    <Refresh className="h-5 w-5 animate-spin" />
                    در حال پاک‌سازی… {fa(Math.round(progress))}٪
                  </>
                )}
                {phase === "done" && (
                  <>
                    <Check className="h-5 w-5" />
                    {fa1(freedRef.current)} گیگابایت آزاد شد
                  </>
                )}
              </span>
            </button>

            {/* انفجار ذرات */}
            {burst.map((p) => (
              <span
                key={p.id}
                className="pointer-events-none absolute left-1/2 top-1/2 z-30 rounded-full"
                style={
                  {
                    width: p.s,
                    height: p.s,
                    background: p.c,
                    boxShadow: `0 0 9px ${p.c}`,
                    "--dx": `${p.dx}px`,
                    "--dy": `${p.dy}px`,
                    animation: "kf-pop 0.85s cubic-bezier(0.16, 1, 0.3, 1) forwards",
                  } as CSSProperties
                }
              />
            ))}
          </div>

          <button
            type="button"
            onClick={quickClean}
            disabled={phase === "run"}
            className="cursor-pointer rounded-xl border border-line px-5 py-3.5 font-medium text-mist transition-all duration-200 hover:border-jade-500/50 hover:text-jade-300 active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-40"
          >
            <span className="flex items-center justify-center gap-2">
              <Zap className="h-[18px] w-[18px]" />
              پاک‌سازی سریع فایل‌های موقت
            </span>
          </button>
        </div>

        {/* تنظیمات کش */}
        <div className="relative mt-8 border-t border-line/60 pt-6">
          <div className="grid gap-x-10 md:grid-cols-2">
            <div>
              <Row title="پاک‌سازی خودکار" desc="کش‌های قدیمی بدون دخالت تو حذف می‌شوند">
                <Toggle checked={autoClean} onChange={setAutoClean} accent="jade" />
              </Row>
              <Row title="بازه پاک‌سازی" desc="زمان‌بندی اجرای پاک‌سازی خودکار">
                <div
                  className={cn(
                    "transition-opacity duration-300",
                    !autoClean && "pointer-events-none opacity-35"
                  )}
                >
                  <Select
                    value={cleanInterval}
                    onChange={setCleanInterval}
                    options={[
                      { value: "7", label: "هر ۷ روز" },
                      { value: "30", label: "هر ۳۰ روز" },
                      { value: "90", label: "هر ۹۰ روز" },
                    ]}
                  />
                </div>
              </Row>
            </div>
            <div>
              <Row
                title="نگه‌داری کش دانلودها"
                desc="فایل‌های نصب برای نصب دوباره سریع‌تر باقی بمانند"
              >
                <Toggle checked={keepDownloads} onChange={setKeepDownloads} />
              </Row>
              <div className="py-4">
                <div className="flex items-center justify-between">
                  <p className="font-medium text-fog">سقف کش</p>
                  <p className="text-[12px] text-dim">حداکثر فضای مجاز</p>
                </div>
                <Slider
                  value={cacheCap}
                  onChange={setCacheCap}
                  min={2}
                  max={16}
                  format={(v) => `${fa(v)} GB`}
                />
              </div>
            </div>
          </div>

          {cacheCap < used && (
            <p
              className="mt-3 flex items-center gap-2.5 rounded-xl border border-ember-500/30 bg-ember-500/10 px-4 py-3 text-[13px] text-ember-200"
              style={{ animation: "kf-drop-in 0.3s ease" }}
            >
              <Info className="h-[18px] w-[18px] shrink-0 text-ember-300" />
              سقف تعیین‌شده کمتر از مصرف فعلی ({fa1(used)} گیگابایت) است — پاک‌سازی خودکار
              اجرا خواهد شد.
            </p>
          )}
        </div>
      </div>
    </TiltCard>
  );
}
