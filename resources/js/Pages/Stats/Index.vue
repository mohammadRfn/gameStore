<template>
    <AppLayout>
        <template #header>
            <h1 class="gs-title">گزارشات فروش</h1>
            <p class="gs-subtitle">سود کالا، درآمد سرویس و وصول فاکتور — از دادهٔ واقعی فروشگاه</p>
        </template>

        <form class="gs-filter" @submit.prevent="apply">
            <div class="gs-filter-ranges">
                <button type="button" v-for="r in ranges" :key="r.id" class="gs-btn"
                    :class="range === r.id ? 'gs-btn-primary' : 'gs-btn-ghost'" @click="setRange(r.id)">{{ r.label }}</button>
            </div>
            <label class="gs-check">
                <input type="checkbox" v-model="paidOnly">
                فقط وصول‌شده
            </label>
            <JalaliDateInput v-model="from" class="gs-date" placeholder="از تاریخ" />
            <JalaliDateInput v-model="to" class="gs-date" placeholder="تا تاریخ" />
            <button type="submit" class="gs-btn gs-btn-primary">اعمال</button>
        </form>

        <div class="gs-tabs">
            <button v-for="t in tabs" :key="t.id" class="gs-tab" :class="{ active: tab === t.id }" @click="tab = t.id">
                {{ t.label }}
            </button>
        </div>

        <!-- ============================================================ -->
        <!-- OVERVIEW                                                      -->
        <!-- ============================================================ -->
        <section v-show="tab === 'overview'">
            <div class="gs-kpi-grid">
                <article class="gs-card gs-kpi" v-for="card in kpiCards" :key="card.title">
                    <p class="gs-label">{{ card.title }}</p>
                    <p class="gs-kpi-val">{{ card.value }}</p>
                    <p class="gs-kpi-delta" :class="card.up ? 'up' : 'down'">
                        {{ card.up ? '▲' : '▼' }} {{ card.delta }} نسبت به دوره قبل
                    </p>
                </article>
            </div>

            <div class="gs-grid-2">
                <div class="gs-card">
                    <h3 class="gs-card-title">روند روزانه — کالا + سرویس</h3>
                    <GsChart type="bar" :stacked="true" :labels="dailyLabels" :datasets="dailyDatasets" :height="260" />
                </div>
                <div class="gs-card">
                    <h3 class="gs-card-title">ترکیب درآمد</h3>
                    <GsChart type="doughnut" :labels="['سود کالا', 'بهای تمام‌شده', 'سرویس']"
                        :datasets="[{ data: mixData }]" :height="260" />
                </div>
            </div>

            <div class="gs-grid-2">
                <div class="gs-card">
                    <h3 class="gs-card-title">سود کالا در برابر درآمد سرویس</h3>
                    <GsChart type="line" :labels="dailyLabels" :datasets="splitDatasets" :height="240" />
                </div>
                <div class="gs-card">
                    <h3 class="gs-card-title">روش پرداخت</h3>
                    <GsChart type="doughnut" :labels="paymentLabels" :datasets="[{ data: paymentData }]"
                        :height="240" />
                </div>
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- PRODUCTS                                                      -->
        <!-- ============================================================ -->
        <section v-show="tab === 'products'">
            <div class="gs-kpi-grid">
                <article class="gs-card gs-kpi">
                    <p class="gs-label">فروش کالا</p>
                    <p class="gs-kpi-val">{{ money(kpi.product_revenue) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">بهای تمام‌شده (خرید)</p>
                    <p class="gs-kpi-val">{{ money(kpi.product_cogs) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">سود ناخالص</p>
                    <p class="gs-kpi-val">{{ money(kpi.product_profit) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">حاشیه</p>
                    <p class="gs-kpi-val">{{ fa(kpi.product_margin) }}٪</p>
                </article>
            </div>

            <div class="gs-grid-2">
                <div class="gs-card">
                    <h3 class="gs-card-title">سود هر کالا</h3>
                    <GsChart type="hbar" :labels="productNames" :datasets="[{ data: productProfits, color: '#4caf7d' }]"
                        :height="280" />
                </div>
                <div class="gs-card">
                    <h3 class="gs-card-title">درآمد در برابر خرید</h3>
                    <GsChart type="bar" :labels="productNames" :datasets="productCompare" :height="280" />
                </div>
            </div>

            <div class="gs-card">
                <h3 class="gs-card-title">جزئیات کالا — وصل به آیتم، فاکتور و گردش انبار</h3>
                <p class="gs-hint">سود هر ردیف = (قیمت فروش روی فاکتور − قیمت خرید کاتالوگ) × تعداد. مرجوعی‌ها حذف شده‌اند.</p>
                <div class="gs-table-wrap">
                    <table class="gs-table">
                        <thead>
                            <tr>
                                <th>کالا</th><th>دسته</th><th>تعداد</th>
                                <th>خرید واحد</th><th>فروش واحد</th>
                                <th>درآمد</th><th>بهای تمام‌شده</th>
                                <th>سود</th><th>حاشیه</th><th>موجودی</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in products" :key="row.item_id || row.name">
                                <td class="strong">{{ row.name }}</td>
                                <td><span class="gs-badge gs-badge-gold">{{ row.category }}</span></td>
                                <td>{{ fa(row.qty) }}</td>
                                <td>{{ money(row.avg_buy) }}</td>
                                <td>{{ money(row.avg_sell) }}</td>
                                <td class="gold">{{ money(row.revenue) }}</td>
                                <td>{{ money(row.cogs) }}</td>
                                <td :class="row.profit >= 0 ? 'ok' : 'bad'">{{ money(row.profit) }}</td>
                                <td>{{ fa(row.margin) }}٪</td>
                                <td>{{ row.stock === null ? '—' : fa(row.stock) }}</td>
                            </tr>
                            <tr v-if="!products.length">
                                <td colspan="10" class="empty">در این بازه فروش کالایی ثبت نشده</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- SERVICES                                                      -->
        <!-- ============================================================ -->
        <section v-show="tab === 'services'">
            <div class="gs-kpi-grid">
                <article class="gs-card gs-kpi">
                    <p class="gs-label">درآمد سرویس</p>
                    <p class="gs-kpi-val">{{ money(kpi.service_revenue) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">هزینه قطعه</p>
                    <p class="gs-kpi-val">{{ money(kpi.service_parts) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">خالص سرویس</p>
                    <p class="gs-kpi-val">{{ money(kpi.service_net) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">تعداد کار</p>
                    <p class="gs-kpi-val">{{ fa(kpi.service_jobs) }}</p>
                </article>
            </div>

            <div class="gs-grid-2">
                <div class="gs-card">
                    <h3 class="gs-card-title">درآمد به تفکیک نوع سرویس</h3>
                    <GsChart type="hbar" :labels="serviceNames"
                        :datasets="[{ data: serviceRevenues, color: '#4c8fe0' }]" :height="260" />
                </div>
                <div class="gs-card">
                    <h3 class="gs-card-title">قیف وضعیت — همهٔ کارها</h3>
                    <GsChart type="doughnut" :labels="funnelLabels" :datasets="[{ data: funnelData }]"
                        :height="260" />
                </div>
            </div>

            <div class="gs-card">
                <h3 class="gs-card-title">جدول سرویس</h3>
                <p class="gs-hint">درآمد از service_jobs.final_price. هزینه قطعه از service_job_items × items.purchase_price.</p>
                <div class="gs-table-wrap">
                    <table class="gs-table">
                        <thead>
                            <tr>
                                <th>نوع سرویس</th><th>تعداد</th><th>میانگین</th>
                                <th>درآمد</th><th>قطعه</th><th>خالص</th><th>باز</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in services" :key="row.service_type_id || row.name">
                                <td class="strong">{{ row.name }}</td>
                                <td>{{ fa(row.jobs) }}</td>
                                <td>{{ money(row.avg) }}</td>
                                <td class="gold">{{ money(row.revenue) }}</td>
                                <td>{{ money(row.parts_cost) }}</td>
                                <td class="ok">{{ money(row.net) }}</td>
                                    <td>
                                        <span v-if="(row.open ?? row.waiting ?? 0) > 0" class="gs-badge gs-badge-warning">{{ fa(row.open ?? row.waiting ?? 0) }}</span>
                                        <span v-else class="gs-badge gs-badge-success">۰</span>
                                    </td>
                                </tr>
                                <tr v-if="!services.length">
                                    <td colspan="7" class="empty">در این بازه سرویسی ثبت نشده</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- INVOICES — محتوای قبلی، بدون تغییر                           -->
        <!-- ============================================================ -->
        <section v-show="tab === 'invoices'">
            <div class="gs-kpi-grid">
                <article class="gs-card gs-kpi">
                    <p class="gs-label">صدور</p>
                    <p class="gs-kpi-val">{{ money(kpi.billed) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">وصول</p>
                    <p class="gs-kpi-val">{{ money(kpi.collected) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">معوق</p>
                    <p class="gs-kpi-val">{{ money(kpi.outstanding) }}</p>
                </article>
                <article class="gs-card gs-kpi">
                    <p class="gs-label">مرجوعی</p>
                    <p class="gs-kpi-val">{{ money(kpi.returned) }}</p>
                </article>
            </div>

            <div class="gs-grid-2">
                <div class="gs-card">
                    <h3 class="gs-card-title">سن مطالبات</h3>
                    <GsChart type="hbar" :labels="agingLabels" :datasets="[{ data: agingTotals, color: '#e05c5c' }]"
                        :height="200" />
                </div>
                <div class="gs-card">
                    <h3 class="gs-card-title">نقشهٔ فروش هفته</h3>
                    <GsChart type="bar" :labels="heatLabels" :datasets="[{ data: heatAmounts, color: '#c9a84c' }]"
                        :height="200" />
                </div>
            </div>

            <div class="gs-card">
                <h3 class="gs-card-title">فاکتورهای بازه</h3>
                <div class="gs-table-wrap">
                    <table class="gs-table">
                        <thead>
                            <tr>
                                <th>شماره</th><th>مشتری</th><th>کالا</th>
                                <th>سرویس</th><th>تعدیل</th><th>نهایی</th><th>وضعیت</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="inv in invoices" :key="inv.id">
                                <td class="strong gold">{{ inv.number }}</td>
                                <td>{{ inv.customer }}</td>
                                <td>{{ money(inv.items) }}</td>
                                <td>{{ money(inv.services) }}</td>
                                <td>{{ money(inv.adjustment) }}</td>
                                <td class="strong">{{ money(inv.total) }}</td>
                                <td>
                                    <span class="gs-badge"
                                        :class="inv.status === 'paid' ? 'gs-badge-success' : inv.status === 'returned' ? 'gs-badge-error' : 'gs-badge-warning'">
                                        {{ statusLabel(inv.status) }}
                                    </span>
                                </td>
                            </tr>
                            <tr v-if="!invoices.length">
                                <td colspan="7" class="empty">فاکتوری در این بازه نیست</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- ============================================================ -->
        <!-- ★★★ رتبه‌بندی — جدید ★★★                                      -->
        <!-- ============================================================ -->
        <section v-show="tab === 'ranking'">

            <!-- نوار فیلتر و سورت -->
            <div class="gs-ranking-toolbar">
                <select class="gs-input" v-model="rankSort">
                    <option value="qty">📊 پرفروش‌ترین (تعداد)</option>
                    <option value="revenue">💰 بیشترین فروش (ریالی)</option>
                    <option value="profit">🏆 پرسودترین</option>
                    <option value="margin">📈 بالاترین حاشیه</option>
                    <option value="avg_sell">💎 گران‌ترین</option>
                    <option value="cogs">📦 بیشترین هزینه</option>
                </select>
                <select class="gs-input" v-model="rankCategory">
                    <option value="">همهٔ دسته‌ها</option>
                    <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
                </select>
                <select class="gs-input" v-model="rankLimit" style="width:80px">
                    <option :value="10">۱۰</option>
                    <option :value="20">۲۰</option>
                    <option :value="50">۵۰</option>
                    <option :value="0">همه</option>
                </select>
            </div>

            <!-- تب‌های رنکینگ -->
            <div class="gs-tabs" style="margin-bottom:1rem">
                <button class="gs-tab" :class="{ active: rankTab === 'product' }" @click="rankTab = 'product'">📦 محصولات</button>
                <button class="gs-tab" :class="{ active: rankTab === 'service' }" @click="rankTab = 'service'">🔧 سرویس‌ها</button>
                <button class="gs-tab" :class="{ active: rankTab === 'dead' }" @click="rankTab = 'dead'">🧊 کندفروش</button>
                <button class="gs-tab" :class="{ active: rankTab === 'category' }" @click="rankTab = 'category'">📊 تفکیک دسته</button>
            </div>

            <!-- ◉ محصولات -->
            <div v-show="rankTab === 'product'">
                <div class="gs-grid-2">
                    <div class="gs-card">
                        <h3 class="gs-card-title">🏆 ۵ تای اول — {{ sortLabel }}</h3>
                        <GsChart type="hbar" :labels="topProductNames"
                            :datasets="[{ data: topProductValues, color: rankSortColor }]" :height="220" />
                    </div>
                    <div class="gs-card">
                        <h3 class="gs-card-title">درآمد در برابر سود — {{ rankLimitTopLabel }}</h3>
                        <GsChart type="bar" :labels="topProductNames"
                            :datasets="[
                                { label: 'درآمد', data: topProductRevenues, color: '#c9a84c' },
                                { label: 'سود', data: topProductProfits, color: '#4caf7d' },
                            ]" :height="220" />
                    </div>
                </div>

                <div class="gs-card">
                    <h3 class="gs-card-title">جدول کامل رتبه‌بندی محصولات</h3>
                    <p class="gs-hint">روی سرستون‌ها کلیک کن تا مرتب شود. فیلتر دسته و سورت از نوار بالا اعمال می‌شود.</p>
                    <div class="gs-table-wrap">
                        <table class="gs-table">
                            <thead>
                                <tr>
                                    <th>رتبه</th>
                                    <th class="sortable" @click="toggleSort('name')">کالا</th>
                                    <th class="sortable" @click="toggleSort('category')">دسته</th>
                                    <th class="sortable" @click="toggleSort('qty')">تعداد ▾</th>
                                    <th class="sortable" @click="toggleSort('avg_sell')">فروش واحد</th>
                                    <th class="sortable" @click="toggleSort('revenue')">درآمد ▾</th>
                                    <th class="sortable" @click="toggleSort('cogs')">هزینه</th>
                                    <th class="sortable" @click="toggleSort('profit')">سود ▾</th>
                                    <th class="sortable" @click="toggleSort('margin')">حاشیه ▾</th>
                                    <th>موجودی</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in rankedProducts" :key="row.item_id || row.name"
                                    :class="{ 'rank-gold': i === 0, 'rank-silver': i === 1, 'rank-bronze': i === 2 }">
                                    <td class="rank-cell">{{ fa(i + 1) }}</td>
                                    <td class="strong">{{ row.name }}</td>
                                    <td><span class="gs-badge gs-badge-gold">{{ row.category }}</span></td>
                                    <td>{{ fa(row.qty) }}</td>
                                    <td>{{ money(row.avg_sell) }}</td>
                                    <td class="gold">{{ money(row.revenue) }}</td>
                                    <td>{{ money(row.cogs) }}</td>
                                    <td :class="row.profit >= 0 ? 'ok' : 'bad'">{{ money(row.profit) }}</td>
                                    <td>{{ fa(row.margin) }}٪</td>
                                    <td>
                                        <span v-if="row.stock !== null && row.stock <= 2 && row.qty > 0" class="gs-badge gs-badge-error">⚠ {{ fa(row.stock) }}</span>
                                        <span v-else-if="row.stock !== null">{{ fa(row.stock) }}</span>
                                        <span v-else class="gs-label">—</span>
                                    </td>
                                </tr>
                                <tr v-if="!rankedProducts.length">
                                    <td colspan="10" class="empty">در این بازه و دسته فروشی ثبت نشده</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ◉ سرویس‌ها -->
            <div v-show="rankTab === 'service'">
                <div class="gs-grid-2">
                    <div class="gs-card">
                        <h3 class="gs-card-title">🏆 پردرآمدترین سرویس‌ها</h3>
                        <GsChart type="hbar" :labels="topServiceNames"
                            :datasets="[{ data: topServiceRevenues, color: '#4c8fe0' }]" :height="220" />
                    </div>
                    <div class="gs-card">
                        <h3 class="gs-card-title">درآمد در برابر خالص</h3>
                        <GsChart type="bar" :labels="topServiceNames"
                            :datasets="[
                                { label: 'درآمد', data: topServiceRevenues, color: '#4c8fe0' },
                                { label: 'خالص', data: topServiceNets, color: '#4caf7d' },
                            ]" :height="220" />
                    </div>
                </div>

                <div class="gs-card">
                    <h3 class="gs-card-title">جدول رتبه‌بندی سرویس‌ها</h3>
                    <p class="gs-hint">درآمد از final_price سرویس‌جاب. خالص = درآمد − جمع قطعات مصرفی از انبار.</p>
                    <div class="gs-table-wrap">
                        <table class="gs-table">
                            <thead>
                                <tr>
                                    <th>رتبه</th>
                                    <th>نوع سرویس</th>
                                    <th>تعداد کار</th>
                                    <th>میانگین</th>
                                    <th>درآمد</th>
                                    <th>هزینه قطعه</th>
                                    <th>خالص</th>
                                    <th>باز</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(row, i) in rankedServices" :key="row.service_type_id || row.name"
                                    :class="{ 'rank-gold': i === 0, 'rank-silver': i === 1, 'rank-bronze': i === 2 }">
                                    <td class="rank-cell">{{ fa(i + 1) }}</td>
                                    <td class="strong">{{ row.name }}</td>
                                    <td>{{ fa(row.jobs) }}</td>
                                    <td>{{ money(row.avg) }}</td>
                                    <td class="gold">{{ money(row.revenue) }}</td>
                                    <td>{{ money(row.parts_cost) }}</td>
                                    <td class="ok">{{ money(row.net) }}</td>
                                    <td>
                                        <span v-if="(row.open ?? row.waiting ?? 0) > 0" class="gs-badge gs-badge-warning">{{ fa(row.open ?? row.waiting ?? 0) }}</span>
                                        <span v-else class="gs-badge gs-badge-success">۰</span>
                                    </td>
                                </tr>
                                <tr v-if="!rankedServices.length">
                                    <td colspan="8" class="empty">سرویسی در این بازه ثبت نشده</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ◉ راکد — محصولاتی با موجودی ولی بدون فروش در بازه -->
            <div v-show="rankTab === 'dead'">
                <div class="gs-card">
                    <h3 class="gs-card-title">🧊 محصولات کندفروش — موجودی بالا نسبت به فروش</h3>
                    <p class="gs-hint">
                        محصولاتی که فروش داشته‌اند ولی موجودی انبارشان حداقل ۳ برابر فروش بازه است.
                        نسبت = موجودی ÷ تعداد فروش.
                    </p>
                    <div class="gs-table-wrap">
                        <table class="gs-table">
                            <thead>
                                <tr>
                                    <th>کالا</th><th>دسته</th><th>فروش در بازه</th>
                                    <th>موجودی</th><th>نسبت</th><th>ارزش منجمد</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in deadStock" :key="row.item_id || row.name">
                                    <td class="strong">{{ row.name }}</td>
                                    <td><span class="gs-badge gs-badge-gold">{{ row.category }}</span></td>
                                    <td>{{ fa(row.qty) }}</td>
                                    <td>
                                        <span class="gs-badge" :class="row.stock >= 10 ? 'gs-badge-error' : 'gs-badge-warning'">
                                            {{ fa(row.stock) }}
                                        </span>
                                    </td>
                                    <td>{{ row.stockRatio }}×</td>
                                    <td class="gold">{{ money(row.stock * row.avg_sell) }}</td>
                                </tr>
                                <tr v-if="!deadStock.length">
                                    <td colspan="6" class="empty">محصول کندفروشی یافت نشد ✓</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- ◉ تفکیک دسته -->
            <div v-show="rankTab === 'category'">
                <div class="gs-grid-2">
                    <div class="gs-card">
                        <h3 class="gs-card-title">📊 درآمد به تفکیک دسته</h3>
                        <GsChart type="doughnut" :labels="categoryLabels"
                            :datasets="[{ data: categoryRevenues }]" :height="260" />
                    </div>
                    <div class="gs-card">
                        <h3 class="gs-card-title">💰 سود به تفکیک دسته</h3>
                        <GsChart type="doughnut" :labels="categoryLabels"
                            :datasets="[{ data: categoryProfits }]" :height="260" />
                    </div>
                </div>

                <div class="gs-card">
                    <h3 class="gs-card-title">جدول تفکیک دسته‌ها</h3>
                    <p class="gs-hint">دسته‌بندی‌ها مناسب فروشگاه کنسول، گیم، لوازم جانبی، موبایل و دیجیتال — از category جدول items خوانده می‌شود.</p>
                    <div class="gs-table-wrap">
                        <table class="gs-table">
                            <thead>
                                <tr>
                                    <th>دسته</th><th>تعداد SKU</th><th>کل تعداد فروش</th>
                                    <th>درآمد</th><th>سود</th><th>حاشیه</th><th>سهم از درآمد</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in categoryRows" :key="row.name">
                                    <td class="strong">{{ row.name }}</td>
                                    <td>{{ fa(row.count) }}</td>
                                    <td>{{ fa(row.qty) }}</td>
                                    <td class="gold">{{ money(row.revenue) }}</td>
                                    <td :class="row.profit >= 0 ? 'ok' : 'bad'">{{ money(row.profit) }}</td>
                                    <td>{{ fa(row.margin) }}٪</td>
                                    <td>
                                        <div class="bar-cell">
                                            <div class="bar-fill" :style="{ width: row.share + '%' }"></div>
                                            <span>{{ fa(row.share) }}٪</span>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="!categoryRows.length">
                                    <td colspan="7" class="empty">دسته‌بندی‌ای ثبت نشده</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>

    </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import JalaliDateInput from '@/Components/JalaliDateInput.vue'
import GsChart from '@/Components/GsChart.vue'

const props = defineProps({
    from:     { type: String, default: '' },
    to:       { type: String, default: '' },
    paidOnly: { type: Boolean, default: true },
    range:    { type: String, default: 'month' },
    kpi:      { type: Object, default: () => ({}) },
    compare:  { type: Object, default: () => ({}) },
    daily:    { type: Array,  default: () => [] },
    products: { type: Array,  default: () => [] },
    services: { type: Array,  default: () => [] },
    invoices: { type: Array,  default: () => [] },
    payments: { type: Array,  default: () => [] },
    aging:    { type: Array,  default: () => [] },
    stock:    { type: Object, default: () => ({}) },
    funnel:   { type: Array,  default: () => [] },
    heatmap:  { type: Array,  default: () => [] },
})

/* ─── State ─── */
const tab      = ref('overview')
const range    = ref(props.range)
const paidOnly = ref(props.paidOnly)
const from     = ref(props.from)
const to       = ref(props.to)

// ★ رتبه‌بندی — state
const rankSort     = ref('profit')
const rankCategory = ref('')
const rankLimit    = ref(10)
const rankTab      = ref('product')

/* ─── Constants ─── */
const ranges = [
    { id: 'today', label: 'امروز' },
    { id: 'week',  label: 'هفته' },
    { id: 'month', label: 'ماه' },
    { id: 'year',  label: 'سال' },
]

const tabs = [
    { id: 'overview', label: 'نمای کلی' },
    { id: 'products', label: 'کالا' },
    { id: 'services', label: 'سرویس' },
    { id: 'invoices', label: 'فاکتور' },
    { id: 'ranking',  label: '🏆 رتبه‌بندی' },
]

/* ─── Helpers ─── */
function fa(n)   { return Number(n || 0).toLocaleString('fa-IR') }
function money(n) { return fa(Math.round(Number(n || 0))) + ' تومان' }

function statusLabel(s) {
    return { paid: 'وصول‌شده', unpaid: 'در انتظار', returned: 'مرجوع' }[s] ?? s
}

function setRange(id) {
    range.value = id
    apply()
}

function apply() {
    router.get(route('stats.index'), {
        from:      from.value || undefined,
        to:        to.value || undefined,
        paid_only: paidOnly.value ? 1 : 0,
        range:     range.value,
    }, { preserveState: true, replace: true })
}

/* ─── KPI helpers ─── */
const kpi     = computed(() => props.kpi)
const compare = computed(() => props.compare)

function delta(field) {
    const c = compare.value?.[field]
    if (!c) return '—'
    return typeof c.pct === 'number' ? Math.abs(c.pct).toFixed(1) + '٪' : '—'
}
function up(field) {
    const c = compare.value?.[field]
    return c ? c.direction === 'up' : true
}
const kpiCards = computed(() => [
    { title: 'درآمد کل',      value: money(kpi.value.gross),           delta: delta('gross'),           up: up('gross') },
    { title: 'سود خالص',      value: money(kpi.value.net_profit),      delta: delta('net_profit'),      up: up('net_profit') },
    { title: 'درآمد کالا',    value: money(kpi.value.product_revenue), delta: delta('product_revenue'), up: up('product_revenue') },
    { title: 'سود کالا',      value: money(kpi.value.product_profit),  delta: delta('product_profit'),  up: up('product_profit') },
    { title: 'درآمد سرویس',   value: money(kpi.value.service_revenue), delta: delta('service_revenue'), up: up('service_revenue') },
    { title: 'وصول‌شده',      value: money(kpi.value.collected),       delta: delta('collected'),       up: up('collected') },
    { title: 'معوق',          value: money(kpi.value.outstanding),     delta: delta('outstanding'),     up: !up('outstanding') },
    { title: 'فاکتورها',      value: fa(kpi.value.invoice_count),      delta: delta('invoice_count'),   up: up('invoice_count') },
])

/* ─── Daily ─── */
const dailyLabels   = computed(() => props.daily.map(d => d.label))
const dailyProducts = computed(() => props.daily.map(d => d.products))
const dailyServices = computed(() => props.daily.map(d => d.services))
const dailyDatasets = computed(() => [
    { label: 'کالا', data: dailyProducts.value, color: '#c9a84c' },
    { label: 'سرویس', data: dailyServices.value, color: '#4c8fe0' },
])
const mixData = computed(() => [
    Number(kpi.value.product_profit || 0),
    Number(kpi.value.product_cogs || 0),
    Number(kpi.value.service_revenue || 0),
])
const splitDatasets = computed(() => [
    { label: 'سود کالا', data: dailyProducts.value, color: '#4caf7d' },
    { label: 'سرویس',   data: dailyServices.value, color: '#4c8fe0' },
])

/* ─── Products ─── */
const products     = computed(() => props.products)
const productNames = computed(() => products.value.map(p => p.name))
const productProfits  = computed(() => products.value.map(p => p.profit))
const productCompare  = computed(() => [
    { label: 'درآمد', data: products.value.map(p => p.revenue), color: '#c9a84c' },
    { label: 'خرید',  data: products.value.map(p => p.cogs),   color: '#e05c5c' },
])

/* ─── Services ─── */
const services      = computed(() => props.services)
const serviceNames  = computed(() => services.value.map(s => s.name))
const serviceRevenues = computed(() => services.value.map(s => s.revenue))

/* ─── Funnel ─── */
const funnelLabels = computed(() => (props.funnel || []).map(f => f.label))
const funnelData   = computed(() => (props.funnel || []).map(f => f.count))

/* ─── Payments ─── */
const paymentLabels = computed(() => (props.payments || []).map(p => p.method))
const paymentData   = computed(() => (props.payments || []).map(p => p.count))

/* ─── Aging ─── */
const agingLabels = computed(() => (props.aging || []).map(a => a.label))
const agingTotals = computed(() => (props.aging || []).map(a => a.total))

/* ─── Heatmap ─── */
const heatLabels = computed(() => (props.heatmap || []).map(h => h.label))
const heatAmounts = computed(() => (props.heatmap || []).map(h => h.amount))


/* ================================================================
   ★★★ رتبه‌بندی — Computed Properties
   ================================================================ */

const sortLabel = computed(() => ({
    qty: 'تعداد فروش', revenue: 'درآمد ریالی', profit: 'سود مطلق',
    margin: 'حاشیه سود', avg_sell: 'قیمت فروش', cogs: 'بهای تمام‌شده',
}[rankSort.value] || 'سود'))

const rankSortColor = computed(() => ({
    qty: '#c9a84c', revenue: '#c9a84c', profit: '#4caf7d',
    margin: '#4c8fe0', avg_sell: '#8b5cf6', cogs: '#e05c5c',
}[rankSort.value] || '#4caf7d'))

const categories = computed(() => {
    const set = new Set(products.value.map(p => p.category).filter(Boolean))
    return [...set].sort()
})

// فیلتر + سورت محصولات
const rankedProducts = computed(() => {
    let arr = [...products.value]
    if (rankCategory.value) {
        arr = arr.filter(p => p.category === rankCategory.value)
    }
    const key = rankSort.value
    const numericKeys = ['qty', 'revenue', 'cogs', 'profit', 'margin', 'avg_sell', 'avg_buy']
    if (numericKeys.includes(key)) {
        arr.sort((a, b) => (Number(b[key]) || 0) - (Number(a[key]) || 0))
    } else if (key === 'name') {
        arr.sort((a, b) => String(a.name).localeCompare(String(b.name), 'fa'))
    }
    if (rankLimit.value > 0) {
        arr = arr.slice(0, rankLimit.value)
    }
    return arr
})

const rankLimitTopLabel = computed(() => {
    const n = Math.min(5, rankedProducts.value.length)
    return fa(n) + ' تای اول'
})

// ۵ تای اول محصول
const top5Products = computed(() => rankedProducts.value.slice(0, 5))
const topProductNames    = computed(() => top5Products.value.map(p => p.name))
const topProductValues   = computed(() => top5Products.value.map(p => p[rankSort.value] ?? 0))
const topProductRevenues = computed(() => top5Products.value.map(p => p.revenue))
const topProductProfits  = computed(() => top5Products.value.map(p => p.profit))

// رتبه‌بندی سرویس — سورت بر اساس revenue
const rankedServices = computed(() => {
    return [...services.value].sort((a, b) => (b.revenue || 0) - (a.revenue || 0))
})

// ۵ تای اول سرویس
const top5Services = computed(() => rankedServices.value.slice(0, 5))
const topServiceNames    = computed(() => top5Services.value.map(s => s.name))
const topServiceRevenues = computed(() => top5Services.value.map(s => s.revenue))
const topServiceNets     = computed(() => top5Services.value.map(s => s.net))

// کندفروش — محصولاتی که فروش داشتند ولی موجودی‌شان نسبت به فروش خیلی بالاست
// (productRows فقط آیتم‌هایی با فروش > ۰ برمی‌گرداند، پس qty === 0 ممکن نیست)
const deadStock = computed(() => {
    const stockMap = props.stock || {}
    return products.value
        .map(p => ({
            ...p,
            stock: stockMap[p.item_id] ?? p.stock ?? 0,
        }))
        .filter(p => p.stock > 0 && p.qty > 0 && p.stock >= p.qty * 3) // موجودی ≥ ۳ برابر فروش
        .map(p => ({
            ...p,
            stockRatio: p.qty > 0 ? (p.stock / p.qty).toFixed(1) : '—',
        }))
        .sort((a, b) => (b.stock * b.avg_sell) - (a.stock * a.avg_sell))
})

// تفکیک دسته
const categoryRows = computed(() => {
    const map = {}
    const totalRevenue = products.value.reduce((s, p) => s + (p.revenue || 0), 0) || 1
    products.value.forEach(p => {
        const cat = p.category || 'بدون دسته'
        if (!map[cat]) map[cat] = { name: cat, count: 0, qty: 0, revenue: 0, cogs: 0, profit: 0 }
        map[cat].count++
        map[cat].qty    += p.qty || 0
        map[cat].revenue += p.revenue || 0
        map[cat].cogs    += p.cogs || 0
        map[cat].profit  += p.profit || 0
    })
    return Object.values(map)
        .map(c => ({
            ...c,
            revenue: Math.round(c.revenue),
            profit:  Math.round(c.profit),
            margin:  c.revenue > 0 ? Math.round(c.profit / c.revenue * 100) : 0,
            share:   Math.round(c.revenue / totalRevenue * 100),
        }))
        .sort((a, b) => b.revenue - a.revenue)
})

const categoryLabels   = computed(() => categoryRows.value.map(c => c.name))
const categoryRevenues = computed(() => categoryRows.value.map(c => c.revenue))
const categoryProfits  = computed(() => categoryRows.value.map(c => c.profit))

function toggleSort(key) {
    rankSort.value = key
}
</script>

<style scoped>
/* ─── همهٔ استایل‌های قبلی ─── */
.gs-toolbar   { display: flex; flex-wrap: wrap; gap: .5rem; margin-bottom: 1rem; }
.gs-kpi-grid  { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: .75rem; margin-bottom: 1rem; }
.gs-num       { font-size: 1.25rem; font-weight: 800; color: var(--gs-gold); }
.gs-grid-2    { display: grid; grid-template-columns: 1.4fr 1fr; gap: 1rem; margin-bottom: 1rem; }
.gs-card-title { margin: 0 0 .75rem; font-size: .95rem; }
.gs-hint      { font-size: .78rem; color: var(--gs-text-secondary); margin: 0 0 .75rem; }
.gs-table-wrap { overflow-x: auto; }
.strong { font-weight: 700; }
.gold   { color: var(--gs-gold); font-weight: 700; }
.ok     { color: var(--gs-success); font-weight: 700; }
.bad    { color: var(--gs-error); font-weight: 700; }
.empty  { text-align: center; color: var(--gs-text-muted); padding: 1.2rem !important; }

@media (max-width: 900px) { .gs-grid-2 { grid-template-columns: 1fr; } }

/* ─── رتبه‌بندی — استایل‌های جدید ─── */
.gs-ranking-toolbar {
    display: flex;
    flex-wrap: wrap;
    gap: .6rem;
    margin-bottom: 1rem;
    padding: .75rem;
    background: var(--gs-bg-elevated);
    border: 1px solid var(--gs-border);
    border-radius: 10px;
}

.gs-ranking-toolbar .gs-input {
    font-size: .82rem;
    padding: .4rem .7rem;
    border-radius: 8px;
    background: var(--gs-bg-card);
    border: 1px solid var(--gs-border);
    color: var(--gs-text-primary);
    cursor: pointer;
}

.gs-ranking-toolbar .gs-input:focus {
    border-color: var(--gs-gold);
    outline: none;
}

.sortable {
    cursor: pointer;
    user-select: none;
    transition: color .2s;
}
.sortable:hover {
    color: var(--gs-gold);
}

.rank-cell {
    font-weight: 800;
    font-size: .9rem;
    width: 40px;
    text-align: center;
}

/* ردیف‌های طلایی/نقره‌ای/برنزی */
.rank-gold   { background: rgba(201, 168, 76, .12); }
.rank-silver { background: rgba(192, 192, 192, .08); }
.rank-bronze { background: rgba(205, 127, 50, .07); }

/* نوار سهم از درآمد */
.bar-cell {
    display: flex;
    align-items: center;
    gap: .4rem;
    min-width: 100px;
}
.bar-fill {
    height: 6px;
    background: linear-gradient(90deg, var(--gs-gold-dark), var(--gs-gold-light));
    border-radius: 4px;
    min-width: 4px;
    transition: width .3s ease;
}
.bar-cell span {
    font-size: .75rem;
    color: var(--gs-text-muted);
    white-space: nowrap;
}

.gs-date {
    width: 155px;
}
</style>
