<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>GameShop</title>
    {{-- اعمال تم قبل از لود CSS برای جلوگیری از فلش (FOUC) --}}
    <script>
        (function () {
            try {
                var t = localStorage.getItem('gs-theme'); // 'dark' | 'light'
                var dark = t ? t === 'dark' : true;
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
</body>
</html>