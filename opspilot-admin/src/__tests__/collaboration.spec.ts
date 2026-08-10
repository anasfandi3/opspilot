import { describe, expect, it } from 'vitest'
import { requestKeys } from '@/features/requests/queries/requestKeys'
import {
  activityText,
  canApplyCollaborationResult,
  commentPayload,
  formatFileSize,
  validateUpload,
} from '@/features/requests/collaboration/helpers'
import type { RequestActivity } from '@/features/requests/collaboration/types'
import { contentDispositionFilename } from '@/lib/api/client'

const activity = (type: string, actor: string | null = 'Anas'): RequestActivity => ({
  id: 1,
  type,
  actor: actor ? { id: 1, name: actor } : null,
  metadata: null,
  comment: null,
  attachment: null,
  approval: null,
  created_at: '2026-08-10T00:00:00Z',
})

describe('request collaboration', () => {
  it('isolates section keys by workspace and request', () => {
    expect(requestKeys.comments(1, 2, 1)).not.toEqual(requestKeys.comments(2, 2, 1))
    expect(requestKeys.attachments(1, 2, 1)).not.toEqual(requestKeys.attachments(1, 3, 1))
    expect(requestKeys.activity(1, 2, 1)).not.toEqual(requestKeys.comments(1, 2, 1))
    expect(requestKeys.comments(1, 2, 1)).not.toEqual(requestKeys.comments(1, 2, 2))
  })
  it('serializes trimmed comment payloads', () =>
    expect(commentPayload('  note  ')).toEqual({ body: 'note' }))
  it('rejects late collaboration mutation results from another workspace', () => {
    expect(canApplyCollaborationResult(1, 2)).toBe(false)
    expect(canApplyCollaborationResult(2, 2)).toBe(true)
  })
  it('formats file sizes and validates backend file constraints', () => {
    expect(formatFileSize(512)).toBe('512 B')
    expect(formatFileSize(1536)).toBe('1.5 KB')
    expect(validateUpload(new File(['ok'], 'note.txt'))).toBe('')
    expect(validateUpload(new File(['bad'], 'script.exe'))).toContain('not supported')
    expect(validateUpload(null)).toBe('Choose a file.')
  })
  it('formats every backend activity type and safe fallbacks', () => {
    expect(activityText(activity('request_created'))).toBe('Anas created the request')
    expect(activityText(activity('request_submitted'))).toContain('submitted')
    expect(activityText(activity('request_cancelled'))).toContain('cancelled')
    expect(activityText(activity('request_approved'))).toContain('approved')
    expect(activityText(activity('request_rejected'))).toContain('rejected')
    expect(activityText(activity('approval_activated', null))).toContain('became pending')
    expect(activityText(activity('approval_approved'))).toContain('approved')
    expect(activityText(activity('approval_rejected'))).toContain('rejected')
    expect(activityText(activity('comment_added'))).toContain('comment')
    expect(activityText(activity('attachment_uploaded'))).toContain('attachment')
    expect(activityText(activity('future_event', null))).toBe('System: future event')
  })
  it('parses backend download filenames with deterministic fallbacks', () => {
    expect(contentDispositionFilename("attachment; filename*=UTF-8''quarterly%20report.pdf")).toBe(
      'quarterly report.pdf',
    )
    expect(contentDispositionFilename('attachment; filename="safe report.pdf"')).toBe(
      'safe report.pdf',
    )
    expect(contentDispositionFilename('attachment; filename=report.csv')).toBe('report.csv')
    expect(contentDispositionFilename(null)).toBeNull()
  })
})
