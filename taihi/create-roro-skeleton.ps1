# -----------------------------------------------
# RoRo Core – skeleton creator (empty files)
# -----------------------------------------------

$paths = @"
wp-content/plugins/roro-core/roro-core.php
wp-content/plugins/roro-core/includes/class-loader.php
wp-content/plugins/roro-core/includes/db/schema.php
wp-content/plugins/roro-core/includes/auth/class-auth-controller.php
wp-content/plugins/roro-core/includes/auth/service-account.json
wp-content/plugins/roro-core/includes/class-post-types.php
wp-content/plugins/roro-core/includes/class-meta.php
wp-content/plugins/roro-core/includes/class-photo-storage.php
wp-content/plugins/roro-core/includes/class-rate-limiter.php
wp-content/plugins/roro-core/includes/class-cron-scheduler.php
wp-content/plugins/roro-core/includes/class-cron-cleanup.php
wp-content/plugins/roro-core/includes/class-notification-service.php
wp-content/plugins/roro-core/includes/class-export-csv.php
wp-content/plugins/roro-core/includes/class-cli-report.php
wp-content/plugins/roro-core/includes/admin/class-menu.php
wp-content/plugins/roro-core/includes/admin/class-dashboard.php
wp-content/plugins/roro-core/includes/admin/class-settings.php
wp-content/plugins/roro-core/includes/api/class-endpoint-gacha.php
wp-content/plugins/roro-core/includes/api/class-endpoint-report.php
wp-content/plugins/roro-core/includes/api/class-endpoint-photo.php
wp-content/plugins/roro-core/includes/api/class-endpoint-facility-search.php
wp-content/plugins/roro-core/includes/api/class-endpoint-breed-stats.php
wp-content/plugins/roro-core/includes/api/class-endpoint-analytics.php
wp-content/plugins/roro-core/includes/api/class-endpoint-preference.php
wp-content/plugins/roro-core/includes/api/class-endpoint-geocode.php
wp-content/plugins/roro-core/includes/api/class-endpoint-dashboard.php
wp-content/plugins/roro-core/templates/email/weekly_advice.php
wp-content/plugins/roro-core/lang/ja.po
wp-content/plugins/roro-core/lang/en_US.po
wp-content/plugins/roro-core/lang/ko.po
wp-content/plugins/roro-core/lang/zh_CN.po
wp-content/plugins/roro-core/scripts/migrate_data.php
wp-content/plugins/roro-core/tests/e2e/gacha.spec.ts
wp-content/plugins/roro-core/tests/e2e/dashboard.spec.ts
wp-content/plugins/roro-core/tests/unit/notification.test.ts
wp-content/plugins/roro-core/jest.setup.ts
wp-content/plugins/roro-core/tailwind.config.ts
wp-content/plugins/roro-core/tailwind.config.js
wp-content/plugins/roro-core/postcss.config.js
wp-content/plugins/roro-core/package.json
wp-content/plugins/roro-core/pnpm-lock.yaml
wp-content/plugins/roro-core/tsconfig.json
wp-content/plugins/roro-core/vite.config.ts
wp-content/plugins/roro-core/.babelrc
wp-content/plugins/roro-core/.storybook/main.ts
wp-content/plugins/roro-core/.storybook/preview.tsx
wp-content/plugins/roro-core/.github/workflows/test.yml
wp-content/plugins/roro-core/.github/workflows/lint.yml
wp-content/plugins/roro-core/docker-compose.yml
wp-content/plugins/roro-core/Dockerfile.dev
wp-content/themes/roro-ui-child/functions.php
wp-content/plugins/roro-core/src/index.tsx
wp-content/plugins/roro-core/src/styles/global.css
wp-content/plugins/roro-core/src/services/apiClient.ts
wp-content/plugins/roro-core/src/services/notificationClient.ts
wp-content/plugins/roro-core/src/context/AuthProvider.tsx
wp-content/plugins/roro-core/src/hooks/useLocale.ts
wp-content/plugins/roro-core/src/hooks/useBreedList.ts
wp-content/plugins/roro-core/src/hooks/usePagination.ts
wp-content/plugins/roro-core/src/hooks/useNotification.ts
wp-content/plugins/roro-core/src/hooks/useFacilitySearch.ts
wp-content/plugins/roro-core/src/components/gacha/GachaWheel.tsx
wp-content/plugins/roro-core/src/components/dashboard/KpiOverview.tsx
wp-content/plugins/roro-core/src/components/dashboard/ActiveUsersCard.tsx
wp-content/plugins/roro-core/src/components/dashboard/PaidUsersCard.tsx
wp-content/plugins/roro-core/src/components/report/ReportWizard.tsx
wp-content/plugins/roro-core/src/components/report/ReportResultChart.tsx
wp-content/plugins/roro-core/src/components/report/steps/Step1Breed.tsx
wp-content/plugins/roro-core/src/components/report/steps/Step2Health.tsx
wp-content/plugins/roro-core/src/components/report/steps/Step3Advice.tsx
wp-content/plugins/roro-core/src/components/report/steps/Step4Location.tsx
wp-content/plugins/roro-core/src/components/report/steps/Step5Summary.tsx
wp-content/plugins/roro-core/src/components/photo/PhotoUploadForm.tsx
wp-content/plugins/roro-core/src/components/map/MapWithPhotos.tsx
wp-content/plugins/roro-core/src/components/map/FacilityList.tsx
wp-content/plugins/roro-core/src/components/settings/ApiKeyForm.tsx
wp-content/plugins/roro-core/src/components/settings/NotificationSettings.tsx
wp-content/plugins/roro-core/src/pages/dashboard/ReportsPage.tsx
wp-content/plugins/roro-core/src/mocks/handlers/notification.ts
frontend/src/auth/socialLogin.ts
frontend/src/auth/lineLogin.ts
frontend/src/index.tsx
frontend/src/App.tsx
frontend/src/env.d.ts
frontend/package.json
frontend/tsconfig.json
frontend/vite.config.ts
frontend/.eslintrc.cjs
frontend/postcss.config.js
frontend/tailwind.config.ts
frontend/.github/actions/build.yml
frontend/.gitignore
"@

$paths -split "`n" | ForEach-Object {
    $_ = $_.Trim()
    if ($_ -eq '') { return }
    $dir = Split-Path $_
    if ($dir) { New-Item -ItemType Directory -Path $dir -Force | Out-Null }
    New-Item -ItemType File -Path $_ -Force | Out-Null
}
Write-Host "All skeleton files created/updated." -ForegroundColor Green
