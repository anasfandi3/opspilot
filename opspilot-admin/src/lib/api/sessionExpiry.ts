export type SessionExpiryHandler = () => void | Promise<void>

let handler: SessionExpiryHandler | null = null
let handling: Promise<void> | null = null

export function configureSessionExpiryHandler(nextHandler: SessionExpiryHandler | null) {
  handler = nextHandler
  handling = null
}

export function handleSessionExpiry(): Promise<void> {
  if (!handler) return Promise.resolve()
  if (handling) return handling
  handling = Promise.resolve()
    .then(handler)
    .finally(() => {
      handling = null
    })
  return handling
}
