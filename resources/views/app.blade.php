<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>GameShop</title>
    {{-- گام ۱: حدس سریع از localStorage تا فلش رنگ نداشته باشیم --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('gs-theme'); // 'dark' | 'light' | 'system'
                var dark = t === 'light' ? false : (t === 'system'
                    ? window.matchMedia('(prefers-color-scheme: dark)').matches
                    : true);
                document.documentElement.classList.toggle('light', !dark);
                document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
            } catch (e) {}
        })();
    </script>
    @routes
    @vite(['resources/js/app.js', 'resources/css/app.css'])
    @inertiaHead
</head>
<body>
    @inertia
    {{-- گام ۲: تصحیح از مقدار واقعیِ سرور (general.theme) پیش از mount شدن Vue --}}
    <script>
        (function () {
            try {
                var page = JSON.parse(document.getElementById('app').dataset.page);
                var st = page.props && page.props.theme;
                if (st === 'light' || st === 'dark' || st === 'system') {
                    var dark = st === 'light' ? false : (st === 'system'
                        ? window.matchMedia('(prefers-color-scheme: dark)').matches
                        : true);
                    document.documentElement.classList.toggle('light', !dark);
                    document.documentElement.setAttribute('data-theme', dark ? 'dark' : 'light');
                    localStorage.setItem('gs-theme', st);
                }
            } catch (e) {}
        })();
    </script>
</body>
</html>