/**
 * Sentry 프론트 통합 (Phase 2-C 묶음 10)
 *
 * DSN 없이 설치·배포 가능. VITE_SENTRY_DSN 환경변수 입력 시 즉시 활성화.
 * Kay 가 Sentry 대시보드에서 프로젝트 생성 후 .env 에 DSN 넣으면 자동 가동.
 */
export function initSentry(app, router) {
  const dsn = import.meta.env.VITE_SENTRY_DSN || ''
  if (!dsn) {
    if (import.meta.env.DEV) {
      // 개발 환경에서는 경고만
      console.info('[Sentry] DSN 미설정 — 에러 수집 비활성화. .env 에 VITE_SENTRY_DSN 추가 시 자동 활성화')
    }
    return
  }

  // 동적 import 로 번들 크기 최적화 (DSN 없으면 아예 로드 안 됨)
  import('@sentry/vue').then((Sentry) => {
    Sentry.init({
      app,
      dsn,
      environment: import.meta.env.MODE || 'production',
      integrations: [
        Sentry.browserTracingIntegration({ router }),
      ],
      tracesSampleRate: parseFloat(import.meta.env.VITE_SENTRY_TRACES_RATE ?? '0.1'),
      // 민감 데이터 마스킹
      beforeSend(event) {
        // 토큰·비밀번호 URL 파라미터 제거
        if (event.request?.url) {
          event.request.url = event.request.url.replace(/([?&])(token|password|api_key|secret)=[^&]*/gi, '$1$2=REDACTED')
        }
        // 헤더 정리
        if (event.request?.headers) {
          delete event.request.headers.Authorization
          delete event.request.headers.Cookie
        }
        return event
      },
      ignoreErrors: [
        'Failed to fetch dynamically imported module',
        'ChunkLoadError',
        'ResizeObserver loop limit exceeded',
      ],
    })
    console.info('[Sentry] 활성화 완료 —', import.meta.env.MODE)
  }).catch(err => {
    console.warn('[Sentry] 동적 로드 실패', err)
  })
}
