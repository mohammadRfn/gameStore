<template>
  <AppLayout>
    <template #header>
      <div class="backup-topline">
        <div>
          <h1 class="gs-title">مرکز پشتیبان‌گیری سلطنتی</h1>
          <p class="gs-subtitle">خروجی و ورودی حرفه‌ای دیتابیس SQLite، فایل‌های CSV و تصاویر فروشگاه</p>
        </div>

        <div class="backup-head-badges">
          <span class="backup-pill">
            <strong>{{ faNumber(stat.total_runs || 0) }}</strong>
            اجرا
          </span>
          <span class="backup-pill" :class="daysTone">
            آخرین بکاپ:
            <b>{{ lastBackupText }}</b>
          </span>
          <span class="backup-pill backup-pill--info">
            {{ defaults.os || 'Desktop' }}
          </span>
        </div>
      </div>
    </template>

    <main class="backup-page">
      <!-- Hero -->
      <section class="backup-hero backup-reveal">
        <div class="backup-hero__content">
          <div>
            <p class="backup-kicker">GAMESTORE · BACKUP COMMAND CENTER</p>
            <h2 class="backup-hero__title">
              بکاپ‌گیری مثل یک
              <span class="backup-gradient-text">گاوصندوق لوکس</span>
            </h2>
            <p class="backup-hero__desc">
              بسته‌های بکاپ کاملاً طبقه‌بندی‌شده، قابل اعتبارسنجی و آماده‌ی بازیابی هستند؛
              دیتابیس به CSV تبدیل می‌شود، تصاویر در پوشه‌های دقیق خودش قرار می‌گیرند،
              و قبل از هر ایمپورت یک نسخه‌ی ایمنی ساخته می‌شود.
            </p>

            <div class="backup-hero__actions">
              <button class="backup-btn backup-btn--gold" type="button" @click="activeTab = 'export'">
                👑 شروع خروجی کامل
              </button>
              <button class="backup-btn backup-btn--info" type="button" @click="activeTab = 'import'">
                🛡 بررسی بسته‌ی ورودی
              </button>
              <button class="backup-btn backup-btn--ghost" type="button" :disabled="api.loading.overview" @click="refreshAll">
                <span v-if="api.loading.overview" class="backup-spinner"></span>
                تازه‌سازی وضعیت
              </button>
            </div>
          </div>

          <BackupOrbitScene />
        </div>
      </section>

      <!-- Stats -->
      <section class="backup-grid backup-grid--4">
        <BackupStatCard
          label="کل اجراها"
          :value="stat.total_runs || 0"
          icon="🕰"
          hint="خروجی‌ها، ورودی‌ها و dry-runها"
          badge="Audit"
          :delay="40"
        />
        <BackupStatCard
          label="موجودیت‌های قابل بکاپ"
          :value="stat.entities_count || api.entities.value?.length || 0"
          icon="🧬"
          hint="جدول‌ها و بخش‌های برنامه"
          tone="blue"
          badge="Schema"
          :delay="100"
        />
        <BackupStatCard
          label="اجرای ناموفق"
          :value="stat.failed_runs || 0"
          icon="⚠"
          hint="برای بررسی دقیق وارد تاریخچه شو"
          tone="red"
          badge="Health"
          :delay="160"
        />
        <BackupStatCard
          label="فضای آزاد مسیر خروجی"
          :value="Number(stat.disk_free_mb || 0) * 1048576"
          icon="💎"
          hint="برای ذخیره بسته‌های حجیم"
          tone="green"
          bytes
          :delay="220"
        />
      </section>

      <!-- Tabs -->
      <nav class="backup-tabs backup-reveal" style="--delay: 120ms" aria-label="بخش‌های بکاپ">
        <button
          v-for="tab in tabs"
          :key="tab.key"
          type="button"
          class="backup-tab"
          :class="{ 'is-active': activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          <span>{{ tab.icon }}</span>
          {{ tab.label }}
        </button>
      </nav>

      <Transition name="backup-pop" mode="out-in">
        <!-- Export -->
        <section v-if="activeTab === 'export'" key="export" class="backup-grid backup-grid--2">
          <div class="backup-grid">
            <section class="backup-glass backup-section">
              <div class="backup-section__head">
                <div>
                  <h3 class="backup-section__title">♛ نوع خروجی</h3>
                  <p class="backup-section__desc">بسته‌ی کامل، فقط دیتابیس، یا فقط تصاویر را انتخاب کن.</p>
                </div>
              </div>

              <div class="relative z-[1] grid gap-3 md:grid-cols-3">
                <button
                  v-for="mode in modeOptions"
                  :key="mode.key"
                  type="button"
                  class="backup-mode-card text-right"
                  :class="{ 'is-active': exportForm.mode === mode.key }"
                  @click="exportForm.mode = mode.key"
                >
                  <span class="backup-mode-card__icon">{{ mode.icon }}</span>
                  <span class="backup-mode-card__title block">{{ mode.label }}</span>
                  <span class="backup-mode-card__hint block">{{ mode.hint }}</span>
                </button>
              </div>
            </section>

            <BackupPathPicker
              v-model="exportForm.destination_path"
              title="مسیر ذخیره خروجی"
              icon="📤"
              label="Destination Path"
              :description="'پوشه‌ای روی سیستم کاربر که بسته‌ی بکاپ داخل آن ساخته می‌شود.'"
              :default-path="defaults.export_root || ''"
              :validated="exportPath.validated"
              :free-space="exportPath.freeSpace"
              :loading="api.loading.validate"
              hint="در اپ Electron بهتر است مسیر از File Dialog نیتیو گرفته شود؛ اگر bridge فعال نبود، مسیر را دستی وارد کن."
              @validate="validateExportPath"
              @bridge-missing="bridgeMissing"
            />

            <section class="backup-glass backup-section">
              <div class="backup-section__head">
                <div>
                  <h3 class="backup-section__title">⚙ تنظیمات خروجی</h3>
                  <p class="backup-section__desc">فیلترها، برچسب و سیاست‌های خروجی را تنظیم کن.</p>
                </div>
              </div>

              <div class="relative z-[1] grid gap-3 md:grid-cols-2">
                <div class="backup-field md:col-span-2">
                  <label>برچسب بسته</label>
                  <input v-model="exportForm.label" class="backup-input" placeholder="مثلاً: پایان ماه، قبل از آپدیت، نسخه مشتری" />
                </div>
                <div class="backup-field">
                  <label>از تاریخ</label>
                  <JalaliDateInput v-model="exportForm.from_date" placeholder="از تاریخ" />
                </div>
                <div class="backup-field">
                  <label>تا تاریخ</label>
                  <JalaliDateInput v-model="exportForm.to_date" placeholder="تا تاریخ" />
                </div>
                <label class="backup-switch">
                  <input v-model="exportForm.include_media" type="checkbox" :disabled="exportForm.mode === 'database'" />
                  <span class="backup-switch__text">
                    <span class="backup-switch__title">همراه تصاویر</span>
                    <span class="backup-switch__hint">کالاها، اقلام فاکتور و رسیدها</span>
                  </span>
                  <span class="backup-switch__track"><span class="backup-switch__dot"></span></span>
                </label>
                <label class="backup-switch">
                  <input v-model="exportForm.include_soft_deleted" type="checkbox" />
                  <span class="backup-switch__text">
                    <span class="backup-switch__title">رکوردهای حذف نرم</span>
                    <span class="backup-switch__hint">برای بکاپ کامل و قابل Audit</span>
                  </span>
                  <span class="backup-switch__track"><span class="backup-switch__dot"></span></span>
                </label>
                <label class="backup-switch">
                  <input v-model="exportForm.include_orphan_media" type="checkbox" />
                  <span class="backup-switch__text">
                    <span class="backup-switch__title">تصاویر یتیم</span>
                    <span class="backup-switch__hint">فایل‌هایی که در دیتابیس ارجاع ندارند</span>
                  </span>
                  <span class="backup-switch__track"><span class="backup-switch__dot"></span></span>
                </label>
                <label class="backup-switch">
                  <input v-model="exportForm.remember_path" type="checkbox" />
                  <span class="backup-switch__text">
                    <span class="backup-switch__title">ذخیره مسیر</span>
                    <span class="backup-switch__hint">برای دفعات بعد در تنظیمات ذخیره شود</span>
                  </span>
                  <span class="backup-switch__track"><span class="backup-switch__dot"></span></span>
                </label>
              </div>
            </section>

            <BackupEntityMatrix
              v-model="selectedEntities"
              :entities="api.entities.value || []"
              :groups="api.groups.value || {}"
            />
          </div>

          <aside class="backup-grid content-start">
            <section class="backup-glass backup-section sticky top-[132px]">
              <div class="backup-section__head">
                <div>
                  <h3 class="backup-section__title">💠 خلاصه خروجی</h3>
                  <p class="backup-section__desc">قبل از اجرا، وضعیت بسته‌ی خروجی را ببین.</p>
                </div>
              </div>

              <div class="relative z-[1] grid gap-3">
                <div class="grid grid-cols-2 gap-2">
                  <div class="rounded-2xl border border-[var(--gs-border-soft)] bg-[var(--gs-glass)] p-3">
                    <p class="text-[0.72rem] text-[var(--gs-text-muted)]">حالت</p>
                    <b>{{ modeMeta(exportForm.mode).icon }} {{ modeMeta(exportForm.mode).label }}</b>
                  </div>
                  <div class="rounded-2xl border border-[var(--gs-border-soft)] bg-[var(--gs-glass)] p-3">
                    <p class="text-[0.72rem] text-[var(--gs-text-muted)]">موجودیت</p>
                    <b>{{ faNumber(selectedEntities.length) }}</b>
                  </div>
                  <div class="rounded-2xl border border-[var(--gs-border-soft)] bg-[var(--gs-glass)] p-3">
                    <p class="text-[0.72rem] text-[var(--gs-text-muted)]">رکورد تقریبی</p>
                    <b>{{ faNumber(selectedRows) }}</b>
                  </div>
                  <div class="rounded-2xl border border-[var(--gs-border-soft)] bg-[var(--gs-glass)] p-3">
                    <p class="text-[0.72rem] text-[var(--gs-text-muted)]">مسیر</p>
                    <b>{{ exportPath.validated ? 'آماده' : 'نیازمند بررسی' }}</b>
                  </div>
                </div>

                <div class="rounded-3xl border border-[var(--gs-border)] bg-[var(--gs-gold-muted)] p-4">
                  <h4 class="font-black text-[var(--gs-text-primary)]">ساختار خروجی</h4>
                  <ul class="mt-2 grid gap-1 text-[0.78rem] leading-7 text-[var(--gs-text-secondary)]">
                    <li>• manifest.json + checksums.sha256</li>
                    <li>• database/[groups]/*.csv</li>
                    <li v-if="exportForm.mode !== 'database'">• media/items, order-items, invoices/receipts</li>
                    <li>• logs/run.log برای Audit</li>
                  </ul>
                </div>

                <button class="backup-btn backup-btn--gold backup-btn--wide" type="button" :disabled="api.loading.export" @click="runExport">
                  <span v-if="api.loading.export" class="backup-spinner"></span>
                  {{ api.loading.export ? 'در حال ساخت بسته...' : 'ساخت بسته بکاپ' }}
                </button>
              </div>
            </section>
          </aside>
        </section>

        <!-- Import -->
        <section v-else-if="activeTab === 'import'" key="import" class="backup-grid backup-grid--2">
          <div class="backup-grid">
            <BackupPathPicker
              v-model="importForm.source_path"
              title="مسیر بسته ورودی"
              icon="📥"
              label="Source Path"
              :description="'پوشه‌ی بسته‌ی بکاپ یا پوشه‌ی تصاویر/CSV که باید تزریق شود.'"
              :default-path="defaults.import_root || ''"
              hint="اگر پوشه‌ی والد را انتخاب کنی، سرویس جدیدترین بسته‌ی دارای manifest.json را تشخیص می‌دهد."
              @validate="inspectPackage"
              @bridge-missing="bridgeMissing"
            />

            <section class="backup-glass backup-section">
              <div class="backup-section__head">
                <div>
                  <h3 class="backup-section__title">🛡 سیاست ایمپورت</h3>
                  <p class="backup-section__desc">قبل از تزریق واقعی، inspect و dry-run پیشنهاد می‌شود.</p>
                </div>
              </div>

              <div class="relative z-[1] grid gap-3">
                <div class="grid gap-3 md:grid-cols-3">
                  <button
                    v-for="mode in modeOptions"
                    :key="mode.key"
                    type="button"
                    class="backup-mode-card text-right"
                    :class="{ 'is-active': importForm.mode === mode.key }"
                    @click="importForm.mode = mode.key"
                  >
                    <span class="backup-mode-card__icon">{{ mode.icon }}</span>
                    <span class="backup-mode-card__title block">{{ mode.label }}</span>
                    <span class="backup-mode-card__hint block">{{ mode.hint }}</span>
                  </button>
                </div>

                <div class="backup-field">
                  <label>استراتژی تزریق دیتابیس</label>
                  <select v-model="importForm.strategy" class="backup-select" :disabled="importForm.mode === 'media'">
                    <option v-for="strategy in strategyOptions" :key="strategy.key" :value="strategy.key">
                      {{ strategy.icon }} {{ strategy.label }} — {{ strategy.hint }}
                    </option>
                  </select>
                </div>

                <div v-if="importForm.strategy === 'replace' && importForm.mode !== 'media'" class="rounded-3xl border border-[rgba(240,106,106,.35)] bg-[var(--gs-error-soft)] p-4">
                  <h4 class="font-black text-[var(--gs-error)]">جایگزینی کامل خطرناک است</h4>
                  <p class="mt-1 text-[0.8rem] leading-7 text-[var(--gs-text-secondary)]">
                    برای ادامه باید عبارت REPLACE را وارد کنی. سرویس قبل از ایمپورت یک بکاپ ایمنی می‌گیرد.
                  </p>
                  <input v-model="importForm.confirmation" class="backup-input mt-3" dir="ltr" placeholder="REPLACE" />
                </div>

                <div class="grid gap-3 md:grid-cols-2">
                  <label class="backup-switch">
                    <input v-model="importForm.safety_backup" type="checkbox" />
                    <span class="backup-switch__text">
                      <span class="backup-switch__title">بکاپ ایمنی قبل از ایمپورت</span>
                      <span class="backup-switch__hint">برای rollback عملیاتی</span>
                    </span>
                    <span class="backup-switch__track"><span class="backup-switch__dot"></span></span>
                  </label>
                  <label class="backup-switch">
                    <input v-model="importForm.verify_checksums" type="checkbox" />
                    <span class="backup-switch__text">
                      <span class="backup-switch__title">اعتبارسنجی checksum</span>
                      <span class="backup-switch__hint">تشخیص فایل ناقص یا دستکاری‌شده</span>
                    </span>
                    <span class="backup-switch__track"><span class="backup-switch__dot"></span></span>
                  </label>
                  <label class="backup-switch">
                    <input v-model="importForm.relink" type="checkbox" />
                    <span class="backup-switch__text">
                      <span class="backup-switch__title">Relink تصاویر</span>
                      <span class="backup-switch__hint">اصلاح مسیر image_path در دیتابیس</span>
                    </span>
                    <span class="backup-switch__track"><span class="backup-switch__dot"></span></span>
                  </label>
                  <label class="backup-switch">
                    <input v-model="importForm.remember_path" type="checkbox" />
                    <span class="backup-switch__text">
                      <span class="backup-switch__title">ذخیره مسیر ورودی</span>
                      <span class="backup-switch__hint">برای استفاده‌های بعدی</span>
                    </span>
                    <span class="backup-switch__track"><span class="backup-switch__dot"></span></span>
                  </label>
                </div>
              </div>
            </section>
          </div>

          <aside class="backup-grid content-start">
            <section class="backup-glass backup-section sticky top-[132px]">
              <div class="backup-section__head">
                <div>
                  <h3 class="backup-section__title">🔍 پیش‌نمایش بسته</h3>
                  <p class="backup-section__desc">قبل از تزریق واقعی، محتوای بسته را بررسی کن.</p>
                </div>
              </div>

              <div class="relative z-[1] grid gap-3">
                <div v-if="api.importInspection.value" class="grid gap-3">
                  <div class="grid grid-cols-2 gap-2">
                    <div class="rounded-2xl border border-[var(--gs-border-soft)] bg-[var(--gs-glass)] p-3">
                      <p class="text-[0.72rem] text-[var(--gs-text-muted)]">رکورد CSV</p>
                      <b>{{ faNumber(api.importInspection.value.total_rows || 0) }}</b>
                    </div>
                    <div class="rounded-2xl border border-[var(--gs-border-soft)] bg-[var(--gs-glass)] p-3">
                      <p class="text-[0.72rem] text-[var(--gs-text-muted)]">فایل رسانه</p>
                      <b>{{ faNumber(api.importInspection.value.media_files || 0) }}</b>
                    </div>
                  </div>
                  <span class="backup-pill" :class="api.importInspection.value.is_compatible ? 'backup-pill--ok' : 'backup-pill--danger'">
                    {{ api.importInspection.value.is_compatible ? 'سازگار با نسخه فعلی' : 'ناسازگار؛ نیازمند بررسی' }}
                  </span>
                  <p class="rounded-2xl border border-[var(--gs-border-soft)] bg-[var(--gs-glass)] p-3 text-[0.76rem] leading-7 text-[var(--gs-text-secondary)]" dir="ltr">
                    {{ api.importInspection.value.source_path }}
                  </p>
                </div>
                <div v-else class="backup-empty-state !min-h-[180px]">
                  <div>
                    <div class="backup-empty-state__icon">🔍</div>
                    <h4 class="backup-empty-state__title">هنوز بسته بررسی نشده</h4>
                    <p class="backup-empty-state__text">اول دکمه‌ی «بررسی بسته» را بزن تا تعداد CSVها، تصاویر و سازگاری manifest مشخص شود.</p>
                  </div>
                </div>

                <div class="grid gap-2 md:grid-cols-3">
                  <button class="backup-btn backup-btn--info" type="button" :disabled="api.loading.inspect" @click="inspectPackage">
                    <span v-if="api.loading.inspect" class="backup-spinner"></span>
                    بررسی بسته
                  </button>
                  <button class="backup-btn backup-btn--ghost" type="button" :disabled="api.loading.import" @click="runDryImport">
                    Dry-run
                  </button>
                  <button class="backup-btn backup-btn--gold" type="button" :disabled="api.loading.import" @click="runImport">
                    <span v-if="api.loading.import && api.busyId.value === 'import'" class="backup-spinner"></span>
                    تزریق واقعی
                  </button>
                </div>
              </div>
            </section>
          </aside>
        </section>

        <!-- History -->
        <section v-else-if="activeTab === 'history'" key="history">
          <BackupRunTimeline
            :runs="api.runs.value || []"
            :filters="api.runFilters"
            :pagination="api.pagination"
            :loading="api.loading.runs"
            @update:filters="updateRunFilters"
            @refresh="api.fetchRuns(api.pagination.current_page)"
            @page="api.fetchRuns"
            @view="openRun"
            @log="(run) => api.downloadLog(run.id)"
            @delete="askDeleteRun"
          />
        </section>

        <!-- Settings -->
        <section v-else key="settings" class="backup-grid backup-grid--2">
          <section class="backup-glass backup-section">
            <div class="backup-section__head">
              <div>
                <h3 class="backup-section__title">⚙ تنظیمات پیش‌فرض</h3>
                <p class="backup-section__desc">مسیرها و رفتار عمومی ماژول بکاپ را تنظیم کن.</p>
              </div>
            </div>

            <div class="relative z-[1] grid gap-3">
              <div class="backup-field">
                <label>مسیر پیش‌فرض خروجی</label>
                <input v-model="settingsForm.export_root_path" class="backup-input" dir="ltr" />
              </div>
              <div class="backup-field">
                <label>مسیر پیش‌فرض ورودی</label>
                <input v-model="settingsForm.import_root_path" class="backup-input" dir="ltr" />
              </div>
              <div class="grid gap-3 md:grid-cols-3">
                <div class="backup-field">
                  <label>Retention</label>
                  <input v-model.number="settingsForm.retention_copies" type="number" min="0" max="200" class="backup-input" />
                </div>
                <div class="backup-field">
                  <label>Chunk Size</label>
                  <input v-model.number="settingsForm.chunk_size" type="number" min="100" max="20000" class="backup-input" />
                </div>
                <div class="backup-field">
                  <label>Null Marker</label>
                  <input v-model="settingsForm.csv_null_marker" class="backup-input" dir="ltr" />
                </div>
              </div>
              <div class="grid gap-3 md:grid-cols-2">
                <label class="backup-switch">
                  <input v-model="settingsForm.include_media" type="checkbox" />
                  <span class="backup-switch__text"><span class="backup-switch__title">تصاویر به‌صورت پیش‌فرض</span></span>
                  <span class="backup-switch__track"><span class="backup-switch__dot"></span></span>
                </label>
                <label class="backup-switch">
                  <input v-model="settingsForm.auto_safety_backup" type="checkbox" />
                  <span class="backup-switch__text"><span class="backup-switch__title">بکاپ ایمنی خودکار</span></span>
                  <span class="backup-switch__track"><span class="backup-switch__dot"></span></span>
                </label>
              </div>
              <button class="backup-btn backup-btn--gold justify-self-start" type="button" :disabled="api.loading.settings" @click="saveSettings">
                <span v-if="api.loading.settings" class="backup-spinner"></span>
                ذخیره تنظیمات
              </button>
            </div>
          </section>

          <section class="backup-glass backup-section">
            <div class="backup-section__head">
              <div>
                <h3 class="backup-section__title">🧾 راهنمای سریع</h3>
                <p class="backup-section__desc">بهترین جریان پیشنهادی برای فروشگاه لوکال.</p>
              </div>
            </div>
            <ol class="relative z-[1] grid gap-3 text-[0.82rem] leading-8 text-[var(--gs-text-secondary)]">
              <li class="rounded-2xl border border-[var(--gs-border-soft)] bg-[var(--gs-glass)] p-3">۱) هفته‌ای حداقل یک «خروجی کامل» روی یک درایو جدا بگیر.</li>
              <li class="rounded-2xl border border-[var(--gs-border-soft)] bg-[var(--gs-glass)] p-3">۲) قبل از آپدیت برنامه، خروجی Database-only کافی نیست؛ تصاویر را هم بگیر.</li>
              <li class="rounded-2xl border border-[var(--gs-border-soft)] bg-[var(--gs-glass)] p-3">۳) قبل از ایمپورت واقعی، همیشه Inspect و Dry-run را اجرا کن.</li>
              <li class="rounded-2xl border border-[var(--gs-border-soft)] bg-[var(--gs-glass)] p-3">۴) مسیرهای خروجی و ورودی را خارج از پوشه نصب برنامه انتخاب کن.</li>
            </ol>
          </section>
        </section>
      </Transition>
    </main>

    <BackupRunDrawer
      v-model="drawerOpen"
      :data="api.selectedRun.value"
      :loading="api.loading.run"
      @download-log="api.downloadLog"
    />

    <BackupConfirmDialog
      v-model="confirm.open"
      :title="confirm.title"
      :message="confirm.message"
      :icon="confirm.icon"
      :tone="confirm.tone"
      :confirm-label="confirm.confirmLabel"
      :confirm-text="confirm.confirmText"
      :with-checkbox="confirm.withCheckbox"
      :loading="Boolean(confirm.loading)"
      @confirm="runConfirm"
    />

    <BackupToaster :toasts="toasts" @dismiss="dismissToast" />
  </AppLayout>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import JalaliDateInput from '@/Components/JalaliDateInput.vue'
import BackupConfirmDialog from '@/Components/Backup/BackupConfirmDialog.vue'
import BackupEntityMatrix from '@/Components/Backup/BackupEntityMatrix.vue'
import BackupOrbitScene from '@/Components/Backup/BackupOrbitScene.vue'
import BackupPathPicker from '@/Components/Backup/BackupPathPicker.vue'
import BackupRunDrawer from '@/Components/Backup/BackupRunDrawer.vue'
import BackupRunTimeline from '@/Components/Backup/BackupRunTimeline.vue'
import BackupStatCard from '@/Components/Backup/BackupStatCard.vue'
import BackupToaster from '@/Components/Backup/BackupToaster.vue'

import {
  BACKUP_MODES,
  IMPORT_STRATEGIES,
  MODE_META,
  STRATEGY_META,
  faNumber,
  formatBytes,
  formatDateTime,
  presentMode,
  useBackupApi,
} from '@/Composables/useBackupApi'

const api = useBackupApi()

const activeTab = ref('export')
const selectedEntities = ref([])
const drawerOpen = ref(false)
const toasts = ref([])
let toastTimer = 1

const exportPath = reactive({ validated: false, freeSpace: '' })

const exportForm = reactive({
  destination_path: '',
  mode: BACKUP_MODES.FULL,
  label: '',
  include_media: true,
  include_soft_deleted: true,
  include_orphan_media: false,
  redact_sensitive: false,
  from_date: '',
  to_date: '',
  remember_path: true,
})

const importForm = reactive({
  source_path: '',
  mode: BACKUP_MODES.FULL,
  strategy: IMPORT_STRATEGIES.MERGE,
  safety_backup: true,
  verify_checksums: true,
  stop_on_error: false,
  relink: true,
  ignore_fk_violations: false,
  confirmation: '',
  remember_path: true,
})

const settingsForm = reactive({
  export_root_path: '',
  import_root_path: '',
  include_media: true,
  include_soft_deleted: true,
  csv_null_marker: '\\N',
  chunk_size: 1000,
  retention_copies: 10,
  auto_safety_backup: true,
  verify_checksums: true,
  default_import_strategy: IMPORT_STRATEGIES.MERGE,
})

const confirm = reactive({
  open: false,
  action: null,
  payload: null,
  title: '',
  message: '',
  icon: '♛',
  tone: 'gold',
  confirmLabel: 'تأیید',
  confirmText: '',
  withCheckbox: false,
  loading: false,
})

const tabs = [
  { key: 'export', label: 'خروجی', icon: '📤' },
  { key: 'import', label: 'ورودی', icon: '📥' },
  { key: 'history', label: 'تاریخچه', icon: '🕰' },
  { key: 'settings', label: 'تنظیمات', icon: '⚙' },
]

const modeOptions = Object.entries(MODE_META).map(([key, meta]) => ({ key, ...meta }))
const strategyOptions = Object.entries(STRATEGY_META).map(([key, meta]) => ({ key, ...meta }))

const stat = computed(() => api.statistics.value || {})
const defaults = computed(() => api.defaults.value || {})
const modeMeta = (mode) => presentMode(mode)

const selectedRows = computed(() => {
  const selected = new Set(selectedEntities.value)
  return (api.entities.value || [])
    .filter((entity) => selected.has(entity.key))
    .reduce((sum, entity) => sum + Number(entity.rows || 0), 0)
})

const lastBackupText = computed(() => {
  if (stat.value.days_since_backup === null || stat.value.days_since_backup === undefined) return 'ندارد'
  if (Number(stat.value.days_since_backup) <= 0) return 'امروز'
  return `${faNumber(stat.value.days_since_backup)} روز پیش`
})

const daysTone = computed(() => {
  const days = Number(stat.value.days_since_backup ?? 999)
  if (days <= 2) return 'backup-pill--ok'
  if (days <= 7) return 'backup-pill--warn'
  return 'backup-pill--danger'
})

watch(() => exportForm.destination_path, () => {
  exportPath.validated = false
  exportPath.freeSpace = ''
})

watch(() => exportForm.mode, (mode) => {
  if (mode === BACKUP_MODES.DATABASE) exportForm.include_media = false
  if (mode === BACKUP_MODES.MEDIA) exportForm.include_media = true
})

watch(() => api.overview.value, () => seedFromOverview())

onMounted(async () => {
  await refreshAll()
})

async function refreshAll() {
  try {
    await api.fetchOverview()
    await api.fetchRuns(1)
    seedFromOverview()
    if (!selectedEntities.value.length) selectAllEntities()
  } catch (e) {
    toast('error', 'دریافت اطلاعات ناموفق بود', e.message)
  }
}

function seedFromOverview() {
  const d = defaults.value
  const s = api.settings.value || {}

  if (!exportForm.destination_path) exportForm.destination_path = d.export_root || ''
  if (!importForm.source_path) importForm.source_path = d.import_root || ''

  settingsForm.export_root_path = s.export_root_path || d.export_root || ''
  settingsForm.import_root_path = s.import_root_path || d.import_root || ''
  settingsForm.include_media = boolValue(s.include_media, true)
  settingsForm.include_soft_deleted = boolValue(s.include_soft_deleted, true)
  settingsForm.csv_null_marker = s.csv_null_marker || '\\N'
  settingsForm.chunk_size = Number(s.chunk_size || 1000)
  settingsForm.retention_copies = Number(s.retention_copies || 10)
  settingsForm.auto_safety_backup = boolValue(s.auto_safety_backup, true)
  settingsForm.verify_checksums = boolValue(s.verify_checksums, true)
  settingsForm.default_import_strategy = s.default_import_strategy || IMPORT_STRATEGIES.MERGE
}

function boolValue(value, fallback) {
  if (value === undefined || value === null || value === '') return fallback
  if (typeof value === 'boolean') return value
  return ['1', 'true', 'yes', 'on'].includes(String(value).toLowerCase())
}

function selectAllEntities() {
  selectedEntities.value = (api.entities.value || [])
    .filter((entity) => entity.available !== false)
    .map((entity) => entity.key)
}

async function validateExportPath() {
  try {
    const result = await api.validateDestination(exportForm.destination_path)
    exportForm.destination_path = result.path || exportForm.destination_path
    exportPath.validated = true
    exportPath.freeSpace = result.free_space_mb ? `${faNumber(result.free_space_mb)} MB` : ''
    toast('success', 'مسیر خروجی معتبر است', result.path)
  } catch (e) {
    toast('error', 'مسیر نامعتبر', e.message)
  }
}

async function runExport() {
  if (!selectedEntities.value.length) {
    toast('warning', 'هیچ بخشی انتخاب نشده', 'حداقل یک موجودیت را برای خروجی انتخاب کن.')
    return
  }

  try {
    const response = await api.exportBackup({ ...exportForm, entities: selectedEntities.value })
    toast('success', 'بسته بکاپ ساخته شد', response?.data?.path || response?.message)
    activeTab.value = 'history'
  } catch (e) {
    toast('error', 'خروجی ناموفق بود', e.message)
  }
}

async function inspectPackage() {
  try {
    const result = await api.inspectImport({ source_path: importForm.source_path })
    toast('success', 'بسته بررسی شد', `${faNumber(result.total_rows || 0)} رکورد و ${faNumber(result.media_files || 0)} فایل رسانه`)  
  } catch (e) {
    toast('error', 'بررسی بسته ناموفق بود', e.message)
  }
}

async function runDryImport() {
  try {
    const response = await api.dryRunImport(importPayload())
    toast('success', 'Dry-run انجام شد', response?.message || 'هیچ تغییری در دیتابیس ذخیره نشد.')
    activeTab.value = 'history'
  } catch (e) {
    toast('error', 'Dry-run ناموفق بود', e.message)
  }
}

async function runImport() {
  if (importForm.strategy === IMPORT_STRATEGIES.REPLACE && importForm.mode !== BACKUP_MODES.MEDIA && importForm.confirmation !== 'REPLACE') {
    toast('warning', 'تأیید لازم است', 'برای جایگزینی کامل عبارت REPLACE را وارد کن.')
    return
  }

  confirm.open = true
  confirm.action = 'import'
  confirm.payload = importPayload()
  confirm.title = 'تأیید تزریق واقعی داده‌ها'
  confirm.message = 'این عملیات می‌تواند دیتابیس و مسیر تصاویر را تغییر دهد. پیشنهاد می‌شود قبل از آن Dry-run انجام شده باشد.'
  confirm.icon = '🛡'
  confirm.tone = importForm.strategy === IMPORT_STRATEGIES.REPLACE ? 'danger' : 'gold'
  confirm.confirmLabel = 'شروع ایمپورت'
  confirm.confirmText = importForm.strategy === IMPORT_STRATEGIES.REPLACE && importForm.mode !== BACKUP_MODES.MEDIA ? 'REPLACE' : ''
  confirm.withCheckbox = false
}

function importPayload() {
  return { ...importForm }
}

async function openRun(run) {
  drawerOpen.value = true
  try {
    await api.fetchRun(run.id)
  } catch (e) {
    toast('error', 'جزئیات اجرا دریافت نشد', e.message)
  }
}

function askDeleteRun(run) {
  confirm.open = true
  confirm.action = 'delete-run'
  confirm.payload = run
  confirm.title = `حذف اجرای #${run.id}`
  confirm.message = 'رکورد گزارش این اجرا حذف می‌شود. در صورت انتخاب گزینه‌ی حذف فایل‌ها، پوشه‌ی فیزیکی بکاپ نیز پاک خواهد شد.'
  confirm.icon = '🗑'
  confirm.tone = 'danger'
  confirm.confirmLabel = 'حذف اجرا'
  confirm.confirmText = ''
  confirm.withCheckbox = run.direction === 'export'
}

async function runConfirm(extra = {}) {
  confirm.loading = true
  try {
    if (confirm.action === 'import') {
      const response = await api.importBackup(confirm.payload)
      toast('success', 'ایمپورت انجام شد', response?.message)
      activeTab.value = 'history'
    }

    if (confirm.action === 'delete-run') {
      await api.deleteRun(confirm.payload.id, Boolean(extra.checked))
      toast('success', 'اجرای بکاپ حذف شد', `شناسه #${confirm.payload.id}`)
    }

    confirm.open = false
  } catch (e) {
    toast('error', 'عملیات ناموفق بود', e.message)
  } finally {
    confirm.loading = false
  }
}

function updateRunFilters(next) {
  Object.assign(api.runFilters, next)
  api.fetchRuns(1).catch((e) => toast('error', 'فیلتر تاریخچه ناموفق بود', e.message))
}

async function saveSettings() {
  try {
    await api.updateSettings({ ...settingsForm })
    toast('success', 'تنظیمات ذخیره شد', 'مسیرها و رفتار پیش‌فرض بکاپ به‌روزرسانی شد.')
  } catch (e) {
    toast('error', 'ذخیره تنظیمات ناموفق بود', e.message)
  }
}

function bridgeMissing() {
  toast('info', 'پل انتخاب پوشه فعال نیست', 'در محیط Electron یک bridge مثل window.electronAPI.selectDirectory اضافه کن؛ فعلاً مسیر را دستی وارد کن.')
}

function toast(type, title, message = '') {
  const id = toastTimer++
  toasts.value.push({ id, type, title, message })
  window.setTimeout(() => dismissToast(id), 5400)
}

function dismissToast(id) {
  toasts.value = toasts.value.filter((item) => item.id !== id)
}
</script>
