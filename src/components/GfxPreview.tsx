import type { CSSProperties } from "react";
import { cn } from "../utils/cn";
import { fa } from "../lib/format";
import { Gamepad } from "./Icons";

export type Quality = "low" | "mid" | "high" | "ultra";

const Q_LABEL: Record<Quality, string> = {
  low: "پایین",
  mid: "متوسط",
  high: "بالا",
  ultra: "اولترا",
};

const FACES = [
  "translateZ(64px)",
  "rotateY(180deg) translateZ(64px)",
  "rotateY(90deg) translateZ(64px)",
  "rotateY(-90deg) translateZ(64px)",
  "rotateX(90deg) translateZ(64px)",
  "rotateX(-90deg) translateZ(64px)",
];

const PARTS = [
  { l: 12, t: 20, s: 4, c: "#ffb44d", d: 0 },
  { l: 82, t: 16, s: 3, c: "#3fd8bb", d: 0.6 },
  { l: 18, t: 72, s: 3, c: "#6cbcff", d: 1.1 },
  { l: 76, t: 68, s: 4, c: "#ffb44d", d: 0.3 },
  { l: 46, t: 8, s: 3, c: "#ffcf82", d: 1.6 },
  { l: 92, t: 44, s: 3, c: "#3fd8bb", d: 0.9 },
  { l: 6, t: 46, s: 4, c: "#ff7a66", d: 1.9 },
  { l: 62, t: 86, s: 3, c: "#6cbcff", d: 0.4 },
  { l: 30, t: 34, s: 2, c: "#8beedd", d: 2.2 },
  { l: 68, t: 30, s: 2, c: "#ffcf82", d: 1.3 },
  { l: 88, t: 80, s: 3, c: "#ffb44d", d: 2.5 },
  { l: 8, t: 86, s: 3, c: "#3fd8bb", d: 0.8 },
  { l: 52, t: 92, s: 2, c: "#ff7a66", d: 1.7 },
  { l: 38, t: 58, s: 2, c: "#a8d8ff", d: 2.8 },
];

/** پیش‌نمایش زنده — مکعب سه‌بعدی که به کیفیت و نرخ فریم واکنش نشان می‌دهد */
export function GfxPreview({
  quality,
  fps,
  vsync,
}: {
  quality: Quality;
  fps: number;
  vsync: boolean;
}) {
  const qIdx = (["low", "mid", "high", "ultra"] as Quality[]).indexOf(quality);
  const effFps = vsync ? Math.min(fps, 60) : fps;
  const dur = Math.max(2.4, 9 - effFps / 30);
  const particleCount = [0, 5, 9, 14][qIdx];
  const detail = [0.06, 0.22, 0.5, 0.95][qIdx];
  const iconOp = [0, 0, 0.4, 0.85][qIdx];
  const filter =
    qIdx === 0 ? "saturate(0.55) blur(0.6px)" : qIdx === 1 ? "saturate(0.85)" : "none";
  const glow =
    qIdx === 3
      ? "0 0 70px rgba(255,154,31,0.22), inset 0 0 60px rgba(255,154,31,0.04)"
      : qIdx === 2
        ? "0 0 50px rgba(63,216,187,0.12)"
        : "none";

  return (
    <div className="relative flex h-full min-h-[340px] flex-col overflow-hidden rounded-[20px] border border-line bg-gradient-to-b from-ink-900 to-ink-950 p-6">
      <div
        className="pointer-events-none absolute inset-0"
        style={{
          background:
            "radial-gradient(360px 230px at 50% 42%, rgba(255,154,31,0.09), transparent 65%)",
        }}
      />

      <div className="relative flex items-center justify-between">
        <p className="text-[13px] font-medium text-mist">پیش‌نمایش زنده موتور</p>
        <span className="flex items-center gap-1.5 rounded-full border border-jade-500/30 bg-jade-500/10 px-2.5 py-1 font-tech text-[10px] tracking-widest text-jade-300">
          <span
            className="h-1.5 w-1.5 rounded-full bg-jade-400"
            style={{ animation: "kf-blink 2s ease-in-out infinite" }}
          />
          REALTIME
        </span>
      </div>

      {/* صحنه سه‌بعدی */}
      <div
        className="cube-stage relative mx-auto my-auto grid h-[220px] w-[220px] place-items-center rounded-full transition-shadow duration-700"
        style={{ "--face-detail": detail, boxShadow: glow } as CSSProperties}
      >
        {PARTS.slice(0, particleCount).map((p, i) => (
          <span
            key={i}
            className="absolute rounded-full"
            style={{
              left: `${p.l}%`,
              top: `${p.t}%`,
              width: p.s,
              height: p.s,
              background: p.c,
              boxShadow: `0 0 ${p.s * 2.5}px ${p.c}`,
              animation: `kf-float ${4.5 + (i % 5)}s ease-in-out ${p.d}s infinite`,
            }}
          />
        ))}

        <div
          className="cube relative h-[128px] w-[128px] transition-[filter] duration-700"
          style={{ "--cube-dur": `${dur.toFixed(2)}s`, filter } as CSSProperties}
        >
          {FACES.map((t) => (
            <div key={t} className="cube-face" style={{ transform: t }}>
              <Gamepad
                className="absolute left-1/2 top-1/2 h-10 w-10 -translate-x-1/2 -translate-y-1/2 text-ember-300 transition-opacity duration-500"
                style={{ opacity: iconOp }}
              />
            </div>
          ))}
        </div>

        <div className="absolute bottom-3 left-1/2 h-5 w-32 -translate-x-1/2 rounded-full bg-black/60 blur-md" />
      </div>

      {/* قرائت‌ها */}
      <div className="relative grid grid-cols-3 gap-2.5">
        <div className="rounded-xl border border-line bg-ink-850/80 px-3 py-2.5 text-center">
          <p className="font-tech text-xl font-bold leading-7 text-jade-300">{fa(effFps)}</p>
          <p className="text-[10.5px] text-dim">FPS</p>
        </div>
        <div className="rounded-xl border border-line bg-ink-850/80 px-3 py-2.5 text-center">
          <p className="text-lg font-bold leading-7 text-ember-300">{Q_LABEL[quality]}</p>
          <p className="text-[10.5px] text-dim">کیفیت</p>
        </div>
        <div className="rounded-xl border border-line bg-ink-850/80 px-3 py-2.5 text-center">
          <p
            className={cn(
              "text-lg font-bold leading-7",
              vsync ? "text-jade-300" : "text-dim"
            )}
          >
            {vsync ? "فعال" : "غیرفعال"}
          </p>
          <p className="text-[10.5px] text-dim">V-SYNC</p>
        </div>
      </div>

      {vsync && fps > 60 && (
        <p className="relative mt-2.5 text-center text-[11px] text-dim">
          V-Sync نرخ فریم را روی {fa(60)} قفل می‌کند
        </p>
      )}
    </div>
  );
}
