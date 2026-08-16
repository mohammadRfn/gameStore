import { useEffect, useRef, useState } from "react";

/** شمارنده انیمیشنی با easing — هنگام تغییر target عدد را نرم دنبال می‌کند */
export function useCountUp(target: number, duration = 900): number {
  const [display, setDisplay] = useState(0);
  const fromRef = useRef(0);
  const rafRef = useRef(0);

  useEffect(() => {
    const from = fromRef.current;
    const start = performance.now();
    cancelAnimationFrame(rafRef.current);

    const tick = (now: number) => {
      const t = Math.min(1, (now - start) / duration);
      const eased = 1 - Math.pow(1 - t, 3);
      const value = from + (target - from) * eased;
      fromRef.current = value;
      setDisplay(value);
      if (t < 1) rafRef.current = requestAnimationFrame(tick);
    };

    rafRef.current = requestAnimationFrame(tick);
    return () => cancelAnimationFrame(rafRef.current);
  }, [target, duration]);

  return display;
}

/** مقدار زنده‌ی درحال نوسان (مثل پینگ) */
export function useJitter(base: number, spread: number, interval = 2400): number {
  const [value, setValue] = useState(base);
  useEffect(() => {
    const id = window.setInterval(() => {
      setValue(Math.round(base + Math.random() * spread));
    }, interval);
    return () => window.clearInterval(id);
  }, [base, spread, interval]);
  return value;
}
