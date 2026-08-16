import { useCallback, useEffect, useState } from "react";
import { cn } from "./utils/cn";
import { fa, fa1 } from "./lib/format";
import { useJitter } from "./hooks";
import { Background } from "./components/Background";
import { GearsCluster } from "./components/Gears";
import { TiltCard } from "./components/TiltCard";
import { CachePanel } from "./components/CachePanel";
import type { PushToast, ToastKind } from "./components/CachePanel";
import { GfxPreview } from "./components/GfxPreview";
import type { Quality } from "./components/GfxPreview";
import {
  Card,
  Reveal,
  Row,
  SectionHead,
  Segmented,
  Select,
  Slider,
  Toggle,
} from "./components/controls";
import {
  Bell,
  Check,
  Close,
  Database,
  Gamepad,
  Gear,
  HardDrive,
  Info,
  LogOut,
  Monitor,
  Refresh,
  Shield,
  SlidersIcon,
  Sparkles,
  Speaker,
  Trash,
  Wifi,
  Globe,
} from "./components/Icons";

/* ---------------- انواع تنظیمات ---------------- */

type Settings = {
  lang: string;
  theme: "dark" | "light" | "amoled";
  reduceMotion: boolean;
  region: string;
  quality: Quality;
  fps: number;
  vsync: boolean;
  lowPower: boolean;
  autoClean: boolean;
  cleanInterval: string;
  cacheCap: number;
  keepDownloads: boolean;
  volume: number;
  ambience: boolean;
  menuMusic: boolean;
  output: string;
  notifDiscounts: boolean;
  notifUpdates: boolean;
  notifFriends: boolean;
  digest: "instant" | "daily" | "weekly";
  onlineStatus: boolean;
  telemetry: boolean;
  ghostMode: boolean;
};

const INITIAL: Settings = {
  lang: "fa",
  theme: "dark",
  reduceMotion: false,
  region: "me",
  quality: "high",
  fps: 144,
  vsync: true,
  lowPower: false,
  autoClean: true,
  cleanInterval: "30",
  cacheCap: 10,
  keepDownloads: true,
  volume: 72,
  ambience: true,
  menuMusic: true,
  output: "stereo",
  notifDiscounts: true,
  notifUpdates: true,
  notifFriends: false,
  digest: "weekly",
  onlineStatus: true,
  telemetry: false,
  ghostMode: false,
};

const SECTIONS = [
  { id: "general", label: "عمومی", icon: Gear },
  { id: "graphics", label: "گرافیک و عملکرد", icon: SlidersIcon },
  { id: "cache", label: "کش و فضای ذخیره", icon: Database },
  { id: "audio", label: "صدا", icon: Speaker },
  { id: "notifications", label: "اعلان‌ها", icon: Bell },
  { id: "privacy", label: "حریم خصوصی", icon: Shield },
];

/* ---------------- کامپوننت‌های کوچک ---------------- */

function EqBars({ volume }: { volume: number }) {
  const muted = volume === 0;
  return (
    <div
      className="flex h-6 items-end gap-[3px] transition-opacity duration-300"
      style={{ opacity: muted ? 0.25 : 1 }}
    >
      {[0, 1, 2, 3, 4].map((i) => (
        <span
          key={i}
          className="w-[3.5px] origin-bottom rounded-full bg-gradient-to-t from-ember-500 to-jade-400"
          style={{
            height: `${8 + ((i * 7) % 16)}px`,
            animation: `kf-eq ${0.5 + i * 0.13}s ease-in-out ${i * 0.07}s infinite alternate`,
            animationPlayState: muted ? "paused" : "running",
            transform: muted ? "scaleY(0.2)" : undefined,
          }}
        />
      ))}
    </div>
  );
}

type ToastItem = { id: number; kind: ToastKind; msg: string };

function ToastHost({ toasts, onClose }: { toasts: ToastItem[]; onClose: (id: number) => void }) {
  const KIND = {
    success: { icon: Check, cls: "border-jade-500/30 bg-jade-500/12 text-jade-300", bar: "#3fd8bb" },
    info: { icon: Info, cls: "border-ice-400/30 bg-ice-400/12 text-ice-300", bar: "#6cbcff" },
    danger: { icon: Trash, cls: "border-coral-500/30 bg-coral-500/12 text-coral-300", bar: "#ff7a66" },
  } as const;

  return (
    <div className="fixed bottom-6 left-5 z-[90] flex w-[min(340px,calc(100vw-2.5rem))] flex-col gap-2.5">
      {toasts.map((t) => {
        const k = KIND[t.kind];
        const Ic = k.icon;
        return (
          <div
            key={t.id}
            className="relative overflow-hidden rounded-xl border border-line bg-ink-850/95 p-3.5 pe-10 shadow-2xl shadow-black/50 backdrop-blur-md"
            style={{ animation: "kf-toast-in 0.35s cubic-bezier(0.22, 1, 0.36, 1)" }}
          >
            <div className="flex items-start gap-3">
              <span
                className={cn("grid h-8 w-8 shrink-0 place-items-center rounded-lg border", k.cls)}
              >
                <Ic className="h-4 w-4" />
              </span>
              <p className="pt-1 text-[13px] leading-6 text-fog">{t.msg}</p>
            </div>
            <button
              type="button"
              onClick={() => onClose(t.id)}
              className="absolute end-2.5 top-2.5 cursor-pointer text-dim transition-colors hover:text-fog"
              aria-label="بستن"
            >
              <Close className="h-4 w-4" />
            </button>
            <span
              className="absolute bottom-0 inset-inline-start-0 h-[3px]"
              style={{ background: k.bar, animation: "kf-toast-bar 3.6s linear forwards" }}
            />
          </div>
        );
      })}
    </div>
  );
}

/* ---------------- اپ ---------------- */

export default function App() {
  const [settings, setSettings] = useState<Settings>(INITIAL);
  const [baseline, setBaseline] = useState<Settings>(INITIAL);
  const [saving, setSaving] = useState(false);
  const [active, setActive] = useState("general");
  const [toasts, setToasts] = useState<ToastItem[]>([]);
  const ping = useJitter(24, 18);

  const set = <K extends keyof Settings>(k: K, v: Settings[K]) =>
    setSettings((s) => ({ ...s, [k]: v }));

  const dirtyCount = (Object.keys(INITIAL) as (keyof Settings)[]).filter(
    (k) => settings[k] !== baseline[k]
  ).length;

  const pushToast: PushToast = useCallback((kind: ToastKind, msg: string) => {
    const id = Date.now() + Math.random();
    setToasts((t) => [...t.slice(-2), { id, kind, msg }]);
    window.setTimeout(() => setToasts((t) => t.filter((x) => x.id !== id)), 3650);
  }, []);

  /* اسکرول‌اسپای بخش‌ها */
  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        for (const e of entries) if (e.isIntersecting) setActive(e.target.id);
      },
      { rootMargin: "-30% 0px -60% 0px" }
    );
    SECTIONS.forEach((s) => {
      const el = document.getElementById(s.id);
      if (el) observer.observe(el);
    });
    return () => observer.disconnect();
  }, []);

  const scrollTo = (id: string) =>
    document.getElementById(id)?.scrollIntoView({ behavior: "smooth", block: "start" });

  const activeIdx = Math.max(0, SECTIONS.findIndex((s) => s.id === active));

  const save = () => {
    if (saving) return;
    setSaving(true);
    window.setTimeout(() => {
      setBaseline({ ...settings });
      setSaving(false);
      pushToast("success", "تنظیمات با موفقیت ذخیره شد");
    }, 900);
  };

  const reset = () => {
    setSettings({ ...baseline });
    pushToast("info", "تغییرات ذخیره‌نشده بازنشانی شد");
  };

  const effFps = settings.vsync ? Math.min(settings.fps, 60) : settings.fps;

  return (
    <div className="relative min-h-screen">
      <Background />

      {/* نوار بالایی */}
      <div className="sticky top-0 z-40 border-b border-line/60 bg-ink-950/80 backdrop-blur-md">
        <div className="mx-auto flex max-w-[1200px] items-center justify-between px-4 py-3.5 md:px-8">
          <div className="flex items-center gap-3">
            <span className="grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-ember-400 to-coral-500 text-ink-950 shadow-glow-ember">
              <Gamepad className="h-5 w-5" />
            </span>
            <div className="leading-none">
              <p className="font-display text-[19px] text-fog">گیم‌استور</p>
              <p className="mt-1 font-tech text-[9px] tracking-[0.32em] text-dim">GAMESTORE</p>
            </div>
          </div>
          <div className="hidden items-center gap-2 text-[13px] text-mist md:flex">
            <span>فروشگاه</span>
            <span className="text-dim">/</span>
            <span className="font-medium text-ember-300">تنظیمات</span>
          </div>
          <div className="flex items-center gap-3">
            <span className="hidden items-center gap-2 rounded-lg border border-line bg-ink-850/70 px-3 py-1.5 font-tech text-[12px] text-mist sm:flex">
              <Wifi className="h-3.5 w-3.5 text-jade-300" />
              {fa(ping)} ms
            </span>
            <button
              type="button"
              className="relative grid h-10 w-10 cursor-pointer place-items-center rounded-full border-2 border-ember-400/40 bg-ink-700 font-display text-lg text-ember-200 transition-transform duration-200 hover:scale-105"
              aria-label="حساب کاربری"
            >
              م
              <span className="absolute -bottom-0.5 -left-0.5 h-3 w-3 rounded-full border-2 border-ink-950 bg-jade-400" />
            </button>
          </div>
        </div>
      </div>

      {/* سربرگ با چرخ‌دنده‌های سه‌بعدی */}
      <header className="relative border-b border-line/50">
        <div className="mx-auto flex max-w-[1200px] items-center justify-between gap-6 px-4 py-10 md:px-8 md:py-14">
          <div className="min-w-0">
            <div className="flex flex-wrap items-center gap-2.5">
              <span className="flex items-center gap-2 rounded-full border border-ember-500/30 bg-ember-500/10 px-3.5 py-1.5 text-[12px] font-medium text-ember-300">
                <Gear className="h-3.5 w-3.5" />
                پنل کنترل فروشگاه
              </span>
              <span className="rounded-full border border-line bg-ink-850/70 px-3.5 py-1.5 font-tech text-[11px] text-mist">
                v2.4.1
              </span>
            </div>
            <h1 className="mt-5 font-display text-[64px] leading-[1.02] text-fog md:text-[84px]">
              تنظیمات
              <svg
                viewBox="0 0 220 14"
                className="mt-1 block h-[10px] w-[190px] md:w-[240px]"
                aria-hidden
              >
                <path
                  d="M4 10 C 60 2, 150 2, 216 8"
                  fill="none"
                  stroke="#ff9a1f"
                  strokeWidth="3.5"
                  strokeLinecap="round"
                  className="draw-path"
                />
              </svg>
            </h1>
            <p className="mt-4 max-w-[440px] text-[14.5px] leading-7 text-mist">
              تجربه فروشگاه را دقیقاً همان‌طور که می‌خواهی کوک کن — از گرافیک و عملکرد تا{" "}
              <span className="font-medium text-ember-300">کش و حافظه</span>.
            </p>
            <div className="mt-6 flex flex-wrap gap-2.5">
              <span className="flex items-center gap-2.5 rounded-xl border border-line bg-ink-850/70 px-3.5 py-2.5 text-[13px] text-mist">
                <Wifi className="h-4 w-4 text-jade-300" />
                پینگ
                <b className="font-tech font-medium text-fog">{fa(ping)} ms</b>
              </span>
              <span className="flex items-center gap-2.5 rounded-xl border border-line bg-ink-850/70 px-3.5 py-2.5 text-[13px] text-mist">
                <HardDrive className="h-4 w-4 text-ember-300" />
                فضای آزاد
                <b className="font-tech font-medium text-fog">{fa1(41.6)} GB</b>
              </span>
              <span className="flex items-center gap-2.5 rounded-xl border border-line bg-ink-850/70 px-3.5 py-2.5 text-[13px] text-mist">
                <Monitor className="h-4 w-4 text-ice-300" />
                سرور
                <span className="flex items-center gap-1.5 font-medium text-jade-300">
                  <span
                    className="h-1.5 w-1.5 rounded-full bg-jade-400"
                    style={{ animation: "kf-blink 2s ease-in-out infinite" }}
                  />
                  آنلاین
                </span>
              </span>
            </div>
          </div>
          <GearsCluster />
        </div>
      </header>

      {/* بدنه */}
      <main className="relative z-10 mx-auto max-w-[1200px] px-4 pb-44 pt-10 md:px-8">
        <div className="lg:grid lg:grid-cols-[250px_1fr] lg:gap-10">
          {/* ناوبری دسکتاپ */}
          <nav className="sticky top-24 hidden self-start lg:block" aria-label="بخش‌های تنظیمات">
            <div className="rounded-[20px] border border-line bg-ink-850/70 p-3 backdrop-blur-sm">
              <div className="relative flex flex-col gap-1">
                <span
                  aria-hidden
                  className="absolute inset-x-1 h-11 rounded-lg border border-ember-500/25 bg-ember-500/12"
                  style={{
                    top: `calc(${activeIdx} * 48px + 4px)`,
                    transition: "top 0.35s cubic-bezier(0.22, 1, 0.36, 1)",
                  }}
                />
                {SECTIONS.map((s) => (
                  <button
                    key={s.id}
                    type="button"
                    onClick={() => scrollTo(s.id)}
                    className={cn(
                      "relative z-10 flex h-11 cursor-pointer items-center gap-3 rounded-lg px-3.5 text-sm transition-colors duration-200",
                      active === s.id
                        ? "font-bold text-ember-300"
                        : "text-mist hover:bg-ink-800/60 hover:text-fog"
                    )}
                  >
                    <s.icon className="h-[18px] w-[18px]" />
                    {s.label}
                  </button>
                ))}
              </div>

              <div className="mt-4 rounded-xl bg-gradient-to-br from-ember-500/50 via-line to-jade-500/40 p-px">
                <div className="rounded-[11px] bg-ink-900 p-4">
                  <div className="flex items-center gap-2">
                    <Sparkles className="h-4 w-4 text-ember-300" />
                    <span className="font-tech text-[10px] tracking-widest text-dim">TIP · 04</span>
                  </div>
                  <p className="mt-2 text-[12.5px] leading-6 text-mist">
                    پاک‌سازی ماهانه کش می‌تواند تا{" "}
                    <span className="font-bold text-jade-300">۲۰٪</span> زمان بارگذاری فروشگاه را
                    کم کند.
                  </p>
                </div>
              </div>
            </div>
          </nav>

          <div className="min-w-0">
            {/* ناوبری موبایل */}
            <div className="-mx-4 mb-8 flex gap-2 overflow-x-auto px-4 pb-1 lg:hidden">
              {SECTIONS.map((s) => (
                <button
                  key={s.id}
                  type="button"
                  onClick={() => scrollTo(s.id)}
                  className={cn(
                    "flex shrink-0 cursor-pointer items-center gap-2 whitespace-nowrap rounded-full border px-4 py-2 text-[13px] transition-colors duration-200",
                    active === s.id
                      ? "border-ember-500/50 bg-ember-500/10 font-bold text-ember-300"
                      : "border-line bg-ink-850/70 text-mist"
                  )}
                >
                  <s.icon className="h-4 w-4" />
                  {s.label}
                </button>
              ))}
            </div>

            <div className="flex flex-col gap-14">
              {/* عمومی */}
              <section id="general" className="scroll-mt-28">
                <Reveal>
                  <SectionHead icon={Globe} title="عمومی" desc="زبان، پوسته و رفتار کلی فروشگاه" />
                </Reveal>
                <Reveal delay={80}>
                  <Card>
                    <Row title="زبان رابط کاربری" desc="زبان منوها، قیمت‌ها و اعلان‌ها">
                      <Select
                        value={settings.lang}
                        onChange={(v) => set("lang", v)}
                        options={[
                          { value: "fa", label: "فارسی" },
                          { value: "en", label: "English" },
                          { value: "ar", label: "العربية" },
                          { value: "de", label: "Deutsch" },
                        ]}
                      />
                    </Row>
                    <Row title="پوسته" desc="ظاهر کلی فروشگاه">
                      <Segmented
                        value={settings.theme}
                        onChange={(v) => set("theme", v)}
                        className="min-w-[250px]"
                        options={[
                          {
                            value: "dark",
                            label: (
                              <>
                                <span className="h-2.5 w-2.5 rounded-full border border-line bg-ink-600" />
                                تیره
                              </>
                            ),
                          },
                          {
                            value: "light",
                            label: (
                              <>
                                <span className="h-2.5 w-2.5 rounded-full bg-fog" />
                                روشن
                              </>
                            ),
                          },
                          {
                            value: "amoled",
                            label: (
                              <>
                                <span className="h-2.5 w-2.5 rounded-full border border-ink-600 bg-black" />
                                AMOLED
                              </>
                            ),
                          },
                        ]}
                      />
                    </Row>
                    <Row title="منطقه سرور" desc="نزدیک‌ترین منطقه برای کاهش تأخیر">
                      <Select
                        value={settings.region}
                        onChange={(v) => set("region", v)}
                        options={[
                          { value: "me", label: "خاورمیانه" },
                          { value: "eu", label: "اروپا" },
                          { value: "asia", label: "آسیای شرقی" },
                          { value: "na", label: "آمریکای شمالی" },
                        ]}
                      />
                    </Row>
                    <Row title="کاهش حرکت" desc="انیمیشن‌های رابط سبک‌تر می‌شوند">
                      <Toggle
                        checked={settings.reduceMotion}
                        onChange={(v) => set("reduceMotion", v)}
                        accent="jade"
                      />
                    </Row>
                    <Row title="نسخه کلاینت" desc="آخرین نسخه نصب‌شده روی دستگاه تو">
                      <div className="flex items-center gap-3">
                        <span className="rounded-lg border border-line bg-ink-900 px-3 py-2 font-tech text-[12px] text-mist">
                          v2.4.1 <span className="text-dim">· build 8812</span>
                        </span>
                        <button
                          type="button"
                          onClick={() => pushToast("success", "شما روی آخرین نسخه هستید")}
                          className="flex cursor-pointer items-center gap-2 rounded-lg border border-line px-3.5 py-2 text-[13px] text-mist transition-all duration-200 hover:border-jade-500/50 hover:text-jade-300 active:scale-95"
                        >
                          <Refresh className="h-4 w-4" />
                          بررسی آپدیت
                        </button>
                      </div>
                    </Row>
                  </Card>
                </Reveal>
              </section>

              {/* گرافیک و عملکرد */}
              <section id="graphics" className="scroll-mt-28">
                <Reveal>
                  <SectionHead
                    icon={SlidersIcon}
                    title="گرافیک و عملکرد"
                    desc="تعادل بین زیبایی و روانی — نتیجه را زنده در مکعب ببین"
                  />
                </Reveal>
                <Reveal delay={80}>
                  <div className="grid gap-6 xl:grid-cols-[1fr_400px]">
                    <Card>
                      <Row title="کیفیت بافت‌ها" desc="جزئیات بصری بازی‌ها و کاورها">
                        <Segmented
                          value={settings.quality}
                          onChange={(v) => set("quality", v)}
                          className="min-w-[300px]"
                          options={[
                            { value: "low", label: "پایین" },
                            { value: "mid", label: "متوسط" },
                            { value: "high", label: "بالا" },
                            { value: "ultra", label: "اولترا" },
                          ]}
                        />
                      </Row>
                      <div className="border-b border-line/60 py-4">
                        <div className="flex items-center justify-between">
                          <div>
                            <p className="font-medium text-fog">نرخ فریم هدف</p>
                            <p className="mt-0.5 text-[13px] text-mist">
                              سقف فریم بر ثانیه برای پیش‌نمایش‌ها
                            </p>
                          </div>
                          <span className="rounded-lg border border-jade-500/30 bg-jade-500/10 px-3 py-1.5 font-tech text-[13px] font-bold text-jade-300">
                            {fa(effFps)} FPS
                          </span>
                        </div>
                        <Slider
                          value={settings.fps}
                          onChange={(v) => set("fps", v)}
                          min={30}
                          max={240}
                          step={6}
                          format={(v) => `${fa(v)} FPS`}
                        />
                      </div>
                      <Row
                        title="V-Sync"
                        desc="همگام‌سازی فریم با نمایشگر و حذف پرش تصویر"
                      >
                        <Toggle checked={settings.vsync} onChange={(v) => set("vsync", v)} accent="jade" />
                      </Row>
                      <Row title="حالت کم‌مصرف" desc="کاهش مصرف باتری و دمای GPU">
                        <Toggle checked={settings.lowPower} onChange={(v) => set("lowPower", v)} />
                      </Row>
                    </Card>

                    <TiltCard className="h-full" max={8}>
                      <GfxPreview quality={settings.quality} fps={settings.fps} vsync={settings.vsync} />
                    </TiltCard>
                  </div>
                </Reveal>
              </section>

              {/* کش و فضای ذخیره */}
              <section id="cache" className="scroll-mt-28">
                <Reveal>
                  <SectionHead
                    icon={Database}
                    title="کش و فضای ذخیره"
                    desc="هر گیگابایت را حساب‌شده خرج کن — پاک‌سازی، سقف و زمان‌بندی"
                  />
                </Reveal>
                <Reveal delay={80}>
                  <CachePanel
                    autoClean={settings.autoClean}
                    setAutoClean={(v) => set("autoClean", v)}
                    cleanInterval={settings.cleanInterval}
                    setCleanInterval={(v) => set("cleanInterval", v)}
                    cacheCap={settings.cacheCap}
                    setCacheCap={(v) => set("cacheCap", v)}
                    keepDownloads={settings.keepDownloads}
                    setKeepDownloads={(v) => set("keepDownloads", v)}
                    pushToast={pushToast}
                  />
                </Reveal>
              </section>

              {/* صدا */}
              <section id="audio" className="scroll-mt-28">
                <Reveal>
                  <SectionHead icon={Speaker} title="صدا" desc="بلندی، موسیقی منو و خروجی صوتی" />
                </Reveal>
                <Reveal delay={80}>
                  <Card>
                    <div className="border-b border-line/60 py-4 first:pt-1">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3.5">
                          <p className="font-medium text-fog">بلندی صدای اصلی</p>
                          <EqBars volume={settings.volume} />
                        </div>
                        <p className="font-tech text-[13px] font-bold text-ember-300">
                          {fa(settings.volume)}٪
                        </p>
                      </div>
                      <Slider
                        value={settings.volume}
                        onChange={(v) => set("volume", v)}
                        min={0}
                        max={100}
                        format={(v) => `${fa(v)}٪`}
                      />
                    </div>
                    <Row title="صدای محیطی فروشگاه" desc="همهمه‌ی ملایم هنگام مرور بازی‌ها">
                      <Toggle checked={settings.ambience} onChange={(v) => set("ambience", v)} accent="jade" />
                    </Row>
                    <Row title="موسیقی منو" desc="پخش موسیقی هنگام حضور در منوها">
                      <Toggle checked={settings.menuMusic} onChange={(v) => set("menuMusic", v)} />
                    </Row>
                    <Row title="خروجی صدا" desc="پیکربندی بلندگوها">
                      <Select
                        value={settings.output}
                        onChange={(v) => set("output", v)}
                        options={[
                          { value: "stereo", label: "استریو" },
                          { value: "surround", label: "محیطی ۵.۱" },
                          { value: "headphone", label: "هدفون" },
                        ]}
                      />
                    </Row>
                  </Card>
                </Reveal>
              </section>

              {/* اعلان‌ها */}
              <section id="notifications" className="scroll-mt-28">
                <Reveal>
                  <SectionHead
                    icon={Bell}
                    title="اعلان‌ها"
                    desc="فقط چیزهایی که واقعاً مهم‌اند خبرت کنند"
                  />
                </Reveal>
                <Reveal delay={80}>
                  <Card>
                    <Row
                      title="تخفیف‌ها و پیشنهادهای ویژه"
                      desc="خبر فوری وقتی بازی موردعلاقه‌ات ارزان می‌شود"
                    >
                      <Toggle
                        checked={settings.notifDiscounts}
                        onChange={(v) => set("notifDiscounts", v)}
                      />
                    </Row>
                    <Row title="به‌روزرسانی بازی‌ها" desc="اعلان هنگام انتشار پچ و آپدیت">
                      <Toggle
                        checked={settings.notifUpdates}
                        onChange={(v) => set("notifUpdates", v)}
                        accent="jade"
                      />
                    </Row>
                    <Row title="پیام‌های دوستان" desc="درخواست دوستی و پیام‌های گروهی">
                      <Toggle
                        checked={settings.notifFriends}
                        onChange={(v) => set("notifFriends", v)}
                      />
                    </Row>
                    <Row title="خلاصه ایمیلی" desc="چند وقت یک‌بار ایمیل بزنیم؟">
                      <Segmented
                        value={settings.digest}
                        onChange={(v) => set("digest", v)}
                        className="min-w-[250px]"
                        options={[
                          { value: "instant", label: "لحظه‌ای" },
                          { value: "daily", label: "روزانه" },
                          { value: "weekly", label: "هفتگی" },
                        ]}
                      />
                    </Row>
                  </Card>
                </Reveal>
              </section>

              {/* حریم خصوصی */}
              <section id="privacy" className="scroll-mt-28">
                <Reveal>
                  <SectionHead
                    icon={Shield}
                    title="حریم خصوصی"
                    desc="دیده‌بانیِ آنچه دیگران از تو می‌بینند"
                  />
                </Reveal>
                <Reveal delay={80}>
                  <Card>
                    <Row title="نمایش وضعیت آنلاین" desc="دوستانت ببینند کی آنلاینی">
                      <Toggle
                        checked={settings.onlineStatus}
                        onChange={(v) => set("onlineStatus", v)}
                      />
                    </Row>
                    <Row
                      title="اشتراک داده‌های تشخیصی"
                      desc="گزارش ناشناس خطاها برای بهبود فروشگاه"
                    >
                      <Toggle checked={settings.telemetry} onChange={(v) => set("telemetry", v)} accent="jade" />
                    </Row>
                    <Row title="حالت روح" desc="آفلاین به‌نظر برس، حتی وقتی آنلاینی">
                      <Toggle checked={settings.ghostMode} onChange={(v) => set("ghostMode", v)} />
                    </Row>
                    <div className="mt-5 flex flex-col gap-4 rounded-xl border border-coral-500/25 bg-coral-500/5 p-4 sm:flex-row sm:items-center sm:justify-between">
                      <div>
                        <p className="font-medium text-coral-300">نشست‌های فعال</p>
                        <p className="mt-0.5 text-[13px] text-mist">
                          ۳ دستگاه دیگر به حساب تو وصل‌اند
                        </p>
                      </div>
                      <button
                        type="button"
                        onClick={() => pushToast("danger", "از همه دستگاه‌های دیگر خارج شدی")}
                        className="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-coral-500/40 px-4 py-2.5 text-[13.5px] font-medium text-coral-300 transition-all duration-200 hover:bg-coral-500/10 hover:shadow-[0_0_24px_rgba(255,90,78,0.15)] active:scale-95"
                      >
                        <LogOut className="h-4 w-4" />
                        خروج از همه دستگاه‌ها
                      </button>
                    </div>
                  </Card>
                </Reveal>
              </section>
            </div>

            {/* پاصفحه */}
            <footer className="mt-16 flex flex-wrap items-center justify-between gap-3 border-t border-line/60 pt-6 text-[13px] text-dim">
              <p>گیم‌استور — پنل تنظیمات · ساخته‌شده برای گیمرها</p>
              <p className="font-tech text-[11px] tracking-wider">BUILD 8812 · v2.4.1 · 2026</p>
            </footer>
          </div>
        </div>
      </main>

      {/* نوار ذخیره شناور */}
      <div className="pointer-events-none fixed inset-x-0 bottom-5 z-[80] flex justify-center px-4">
        <div
          className={cn(
            "flex items-center gap-4 rounded-2xl border border-ember-500/30 bg-ink-900/95 py-3 pe-3 ps-5 shadow-[0_16px_60px_-10px_rgba(255,154,31,0.3)] backdrop-blur-md transition-all duration-500",
            dirtyCount > 0
              ? "pointer-events-auto translate-y-0 opacity-100"
              : "pointer-events-none translate-y-24 opacity-0"
          )}
          style={{ transitionTimingFunction: "cubic-bezier(0.22, 1, 0.36, 1)" }}
        >
          <span className="relative flex h-2.5 w-2.5">
            <span className="absolute inline-flex h-full w-full animate-ping rounded-full bg-ember-400 opacity-60" />
            <span className="relative inline-flex h-2.5 w-2.5 rounded-full bg-ember-400" />
          </span>
          <p className="whitespace-nowrap text-[13.5px] font-medium text-fog">
            {fa(dirtyCount)} تغییر ذخیره‌نشده
          </p>
          <div className="h-6 w-px bg-line" />
          <button
            type="button"
            onClick={reset}
            className="cursor-pointer whitespace-nowrap rounded-xl border border-line px-4 py-2.5 text-[13px] text-mist transition-all duration-200 hover:border-coral-500/40 hover:text-coral-300 active:scale-95"
          >
            بازنشانی
          </button>
          <button
            type="button"
            onClick={save}
            disabled={saving}
            className="shine-btn flex cursor-pointer items-center gap-2 whitespace-nowrap rounded-xl bg-gradient-to-l from-ember-400 to-ember-600 px-5 py-2.5 text-[13.5px] font-bold text-ink-950 shadow-glow-ember transition-all duration-200 hover:brightness-110 active:scale-95 disabled:opacity-70"
          >
            {saving ? (
              <>
                <span className="h-4 w-4 animate-spin rounded-full border-2 border-ink-950/30 border-t-ink-950" />
                در حال ذخیره…
              </>
            ) : (
              <>
                <Check className="h-4 w-4" />
                ذخیره تغییرات
              </>
            )}
          </button>
        </div>
      </div>

      <ToastHost toasts={toasts} onClose={(id) => setToasts((t) => t.filter((x) => x.id !== id))} />
    </div>
  );
}
