import { useMemo } from "react";

type Dust = {
  left: number;
  size: number;
  delay: number;
  dur: number;
  o: number;
  c: string;
};

export function Background() {
  const dust = useMemo<Dust[]>(
    () =>
      Array.from({ length: 26 }, (_, i) => ({
        left: Math.random() * 100,
        size: 2 + Math.random() * 2.6,
        delay: Math.random() * 22,
        dur: 15 + Math.random() * 15,
        o: 0.16 + Math.random() * 0.4,
        c:
          i % 4 === 0
            ? "rgba(255,180,77,0.75)"
            : i % 4 === 1
              ? "rgba(63,216,187,0.65)"
              : "rgba(168,216,255,0.55)",
      })),
    []
  );

  return (
    <div aria-hidden className="pointer-events-none fixed inset-0 z-0 overflow-hidden">
      {/* لایه‌های نور پایه */}
      <div
        className="absolute inset-0"
        style={{
          background:
            "radial-gradient(1100px 620px at 78% -10%, rgba(255,154,31,0.11), transparent 60%)," +
            "radial-gradient(900px 600px at 10% 16%, rgba(63,216,187,0.08), transparent 55%)," +
            "radial-gradient(1200px 800px at 50% 118%, rgba(108,188,255,0.07), transparent 62%)," +
            "linear-gradient(#06090f, #080d18)",
        }}
      />

      {/* هاله‌های شناور */}
      <div
        className="absolute -top-36 left-1/4 h-[480px] w-[480px] rounded-full bg-ember-500/10 blur-[110px]"
        style={{ animation: "kf-drift-a 17s ease-in-out infinite alternate" }}
      />
      <div
        className="absolute -bottom-24 right-[6%] h-[430px] w-[430px] rounded-full bg-jade-500/10 blur-[105px]"
        style={{ animation: "kf-drift-b 21s ease-in-out infinite alternate" }}
      />

      {/* کف شبکه‌ای سه‌بعدی */}
      <div className="absolute inset-x-0 bottom-0 h-[380px] [perspective:430px]">
        <div className="bg-grid-floor absolute -inset-x-[45%] top-0 -bottom-[18%] [transform:rotateX(58deg)]" />
      </div>

      {/* ذرات معلق */}
      {dust.map((d, i) => (
        <span
          key={i}
          className="absolute rounded-full"
          style={
            {
              left: `${d.left}%`,
              bottom: "-6vh",
              width: d.size,
              height: d.size,
              background: d.c,
              boxShadow: `0 0 ${d.size * 3.2}px ${d.c}`,
              animation: `kf-rise ${d.dur}s linear ${d.delay}s infinite`,
              "--dust-o": d.o,
            } as React.CSSProperties
          }
        />
      ))}

      {/* نویز و وینیت */}
      <div className="noise-layer absolute inset-0 opacity-[0.05]" />
      <div
        className="absolute inset-0"
        style={{
          background:
            "radial-gradient(ellipse 92% 72% at 50% 38%, transparent 55%, rgba(4,6,11,0.78))",
        }}
      />
    </div>
  );
}
