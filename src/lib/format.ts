const nf = new Intl.NumberFormat("fa-IR");
const nf1 = new Intl.NumberFormat("fa-IR", {
  minimumFractionDigits: 1,
  maximumFractionDigits: 1,
});

/** اعداد فارسی بدون اعشار */
export function fa(n: number | string): string {
  return typeof n === "string" ? n.replace(/[0-9]/g, (d) => "۰۱۲۳۴۵۶۷۸۹"[+d]) : nf.format(n);
}

/** اعداد فارسی با یک رقم اعشار */
export function fa1(n: number): string {
  return nf1.format(n);
}

/** درصد فارسی */
export function faPct(n: number): string {
  return `${nf.format(Math.round(n))}٪`;
}
