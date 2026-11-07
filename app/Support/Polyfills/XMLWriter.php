<?php

/**
 * جایگزین خالص‌-PHP برای کلاس XMLWriter.
 *
 * PHP باندل‌شدهٔ نسخهٔ دسکتاپ (NativePHP) اکستنشن xmlwriter ندارد، ولی PhpSpreadsheet
 * (موتور ساخت فایل‌های اکسل) برای نوشتن .xlsx به آن نیاز دارد. این فایل فقط وقتی کلاس
 * اصلی وجود نداشته باشد کلاس هم‌نام را تعریف می‌کند؛ روی سیستمی که اکستنشن را دارد
 * (مثل محیط توسعه) هیچ اتفاقی نمی‌افتد.
 *
 * فقط زیرمجموعه‌ای که PhpSpreadsheet استفاده می‌کند پیاده شده؛ خروجی بدون indent و فشرده است.
 */
if (!class_exists('XMLWriter', false)) {
    class XMLWriter
    {
        private string $buffer = '';

        /** @var resource|null */
        private $handle = null;

        /** @var string[] */
        private array $stack = [];

        /** آخرین تگ شروع هنوز با '>' بسته نشده */
        private bool $tagOpen = false;

        private bool $inAttribute = false;

        public function openMemory(): bool
        {
            $this->buffer = '';
            $this->handle = null;

            return true;
        }

        public function openUri(string $uri): bool
        {
            $h = @fopen($uri, 'wb');
            if ($h === false) {
                return false;
            }

            $this->handle = $h;
            $this->buffer = '';

            return true;
        }

        public function setIndent(bool $enable): bool
        {
            return true; // خروجی همیشه فشرده است
        }

        public function setIndentString(string $indentation): bool
        {
            return true;
        }

        public function startDocument(?string $version = '1.0', ?string $encoding = null, ?string $standalone = null): bool
        {
            $decl = '<?xml version="' . ($version ?? '1.0') . '"';
            if ($encoding !== null) {
                $decl .= ' encoding="' . $encoding . '"';
            }
            if ($standalone !== null) {
                $decl .= ' standalone="' . $standalone . '"';
            }

            $this->buffer .= $decl . "?>\n";

            return true;
        }

        public function endDocument(): bool
        {
            while ($this->stack) {
                $this->endElement();
            }

            return true;
        }

        /* ---------------- عناصر ---------------- */

        public function startElement(string $name): bool
        {
            $this->closeStartTag();
            $this->buffer .= '<' . $name;
            $this->stack[] = $name;
            $this->tagOpen = true;

            return true;
        }

        public function startElementNs(?string $prefix, string $name, ?string $namespace): bool
        {
            $qualified = ($prefix !== null && $prefix !== '') ? $prefix . ':' . $name : $name;
            $this->startElement($qualified);

            if ($namespace !== null) {
                $attr = ($prefix !== null && $prefix !== '') ? 'xmlns:' . $prefix : 'xmlns';
                $this->writeAttribute($attr, $namespace);
            }

            return true;
        }

        public function endElement(): bool
        {
            if (!$this->stack) {
                return false;
            }

            $name = array_pop($this->stack);

            if ($this->tagOpen) {
                $this->buffer .= '/>';
                $this->tagOpen = false;
            } else {
                $this->buffer .= '</' . $name . '>';
            }

            return true;
        }

        public function fullEndElement(): bool
        {
            if (!$this->stack) {
                return false;
            }

            $this->closeStartTag();
            $name = array_pop($this->stack);
            $this->buffer .= '</' . $name . '>';

            return true;
        }

        public function writeElement(string $name, ?string $content = null): bool
        {
            $this->startElement($name);

            if ($content !== null && $content !== '') {
                $this->text($content);
            }

            return $this->endElement();
        }

        public function writeElementNs(?string $prefix, string $name, ?string $namespace, ?string $content = null): bool
        {
            $this->startElementNs($prefix, $name, $namespace);

            if ($content !== null && $content !== '') {
                $this->text($content);
            }

            return $this->endElement();
        }

        /* ---------------- ویژگی‌ها ---------------- */

        public function writeAttribute(string $name, string $value): bool
        {
            if (!$this->tagOpen) {
                return false;
            }

            $this->buffer .= ' ' . $name . '="' . $this->escapeAttribute($value) . '"';

            return true;
        }

        public function writeAttributeNs(?string $prefix, string $name, ?string $namespace, string $value): bool
        {
            $qualified = ($prefix !== null && $prefix !== '') ? $prefix . ':' . $name : $name;

            return $this->writeAttribute($qualified, $value);
        }

        public function startAttribute(string $name): bool
        {
            if (!$this->tagOpen) {
                return false;
            }

            $this->buffer .= ' ' . $name . '="';
            $this->inAttribute = true;

            return true;
        }

        public function endAttribute(): bool
        {
            if (!$this->inAttribute) {
                return false;
            }

            $this->buffer .= '"';
            $this->inAttribute = false;

            return true;
        }

        /* ---------------- محتوا ---------------- */

        public function text(string $content): bool
        {
            if ($this->inAttribute) {
                $this->buffer .= $this->escapeAttribute($content);

                return true;
            }

            $this->closeStartTag();
            $this->buffer .= $this->escapeText($content);

            return true;
        }

        public function writeRaw(string $content): bool
        {
            $this->closeStartTag();
            $this->buffer .= $content;

            return true;
        }

        public function writeCdata(string $content): bool
        {
            $this->closeStartTag();
            $this->buffer .= '<![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $content) . ']]>';

            return true;
        }

        public function writeComment(string $content): bool
        {
            $this->closeStartTag();
            $this->buffer .= '<!--' . $content . '-->';

            return true;
        }

        public function writePi(string $target, string $content): bool
        {
            $this->closeStartTag();
            $this->buffer .= '<?' . $target . ' ' . $content . '?>';

            return true;
        }

        /* ---------------- خروجی ---------------- */

        /** @return string|int */
        public function flush(bool $empty = true): string|int
        {
            if ($this->handle !== null) {
                $written = strlen($this->buffer);
                fwrite($this->handle, $this->buffer);
                fflush($this->handle);
                $this->buffer = '';

                return $written;
            }

            $out = $this->buffer;
            if ($empty) {
                $this->buffer = '';
            }

            return $out;
        }

        public function outputMemory(bool $flush = true): string
        {
            $out = $this->buffer;
            if ($flush) {
                $this->buffer = '';
            }

            return $out;
        }

        public function __destruct()
        {
            if ($this->handle !== null) {
                if ($this->buffer !== '') {
                    fwrite($this->handle, $this->buffer);
                    $this->buffer = '';
                }
                fclose($this->handle);
                $this->handle = null;
            }
        }

        /* ---------------- کمکی ---------------- */

        private function closeStartTag(): void
        {
            if ($this->tagOpen) {
                $this->buffer .= '>';
                $this->tagOpen = false;
            }
        }

        private function escapeText(string $s): string
        {
            return str_replace(['&', '<', '>', "\r"], ['&amp;', '&lt;', '&gt;', '&#13;'], $s);
        }

        private function escapeAttribute(string $s): string
        {
            return str_replace(
                ['&', '<', '>', '"', "\n", "\r", "\t"],
                ['&amp;', '&lt;', '&gt;', '&quot;', '&#10;', '&#13;', '&#9;'],
                $s
            );
        }
    }
}
