; ===========================================================================
;  نصب‌کننده و حذف‌کننده‌ی فارسی (راست‌چین)
;  این فایل توسط electron-builder (nsis.include) داخل اسکریپت NSIS قرار می‌گیرد.
;  فایل باید UTF-8 همراه BOM ذخیره شود.
; ===========================================================================

!macro customHeader
  ; زبان فارسی (Farsi / LCID 1065) کنار انگلیسی اضافه می‌شود؛ فایل زبانش راست‌چین است
  !insertmacro MUI_LANGUAGE "Farsi"

  ; متن‌های اختصاصی electron-builder که ترجمه‌ی فارسی ندارند
  LangString win7Required ${LANG_FARSI} "نیاز به ویندوز ۷ یا بالاتر است"
  LangString x64WinRequired ${LANG_FARSI} "نیاز به ویندوز ۶۴ بیتی است"
  LangString appRunning ${LANG_FARSI} "${PRODUCT_NAME} در حال اجراست.$\r$\nبرای بستن آن روی «تأیید» بزنید.$\r$\nاگر بسته نشد، آن را به‌صورت دستی ببندید."
  LangString appCannotBeClosed ${LANG_FARSI} "${PRODUCT_NAME} بسته نمی‌شود.$\r$\nلطفاً آن را به‌صورت دستی ببندید و برای ادامه روی «تلاش مجدد» بزنید."
  LangString installing ${LANG_FARSI} "در حال نصب، لطفاً صبر کنید..."
  LangString areYouSureToUninstall ${LANG_FARSI} "آیا از حذف ${PRODUCT_NAME} مطمئن هستید؟"
  LangString decompressionFailed ${LANG_FARSI} "استخراج فایل‌ها ناموفق بود. لطفاً نصب‌کننده را دوباره اجرا کنید."
  LangString uninstallFailed ${LANG_FARSI} "حذف فایل‌های نسخه‌ی قبلی ناموفق بود. لطفاً نصب‌کننده را دوباره اجرا کنید."
  LangString appClosing ${LANG_FARSI} "در حال بستن ${PRODUCT_NAME}..."

  LangString chooseInstallationOptions ${LANG_FARSI} "انتخاب گزینه‌های نصب"
  LangString chooseUninstallationOptions ${LANG_FARSI} "انتخاب گزینه‌های حذف"
  LangString whichInstallationShouldBeRemoved ${LANG_FARSI} "کدام نصب باید حذف شود؟"
  LangString whoShouldThisApplicationBeInstalledFor ${LANG_FARSI} "این برنامه برای چه کسانی نصب شود؟"
  LangString selectUserMode ${LANG_FARSI} "لطفاً انتخاب کنید این نرم‌افزار برای همه‌ی کاربران در دسترس باشد یا فقط برای خودتان."
  LangString whichInstallationRemove ${LANG_FARSI} "این نرم‌افزار هم برای همه‌ی کاربران و هم برای کاربر فعلی نصب شده است.$\r$\nکدام نصب حذف شود؟"
  LangString freshInstallForAll ${LANG_FARSI} "نصب تازه برای همه‌ی کاربران (رمز مدیر سیستم لازم است)"
  LangString freshInstallForCurrent ${LANG_FARSI} "نصب تازه فقط برای کاربر فعلی"
  LangString onlyForMe ${LANG_FARSI} "فقط برای من"
  LangString forAll ${LANG_FARSI} "همه‌ی کاربران این رایانه"
  LangString loginWithAdminAccount ${LANG_FARSI} "برای ادامه باید با حسابی وارد شوید که عضو گروه مدیران سیستم باشد..."
  LangString perUserInstallExists ${LANG_FARSI} "یک نصب برای کاربر فعلی از قبل وجود دارد."
  LangString perUserInstall ${LANG_FARSI} "یک نصب برای کاربر فعلی وجود دارد."
  LangString perMachineInstallExists ${LANG_FARSI} "یک نصب برای همه‌ی کاربران از قبل وجود دارد."
  LangString perMachineInstall ${LANG_FARSI} "یک نصب برای همه‌ی کاربران وجود دارد."
  LangString reinstallUpgrade ${LANG_FARSI} "نصب مجدد / به‌روزرسانی انجام می‌شود."
  LangString uninstall ${LANG_FARSI} "حذف انجام می‌شود."
!macroend

; زبان نصب‌کننده همیشه فارسی باشد (مستقل از زبان ویندوز مشتری)
!macro customInit
  StrCpy $LANGUAGE ${LANG_FARSI}
!macroend

; زبان حذف‌کننده هم فارسی
!macro customUnInit
  StrCpy $LANGUAGE ${LANG_FARSI}
!macroend
