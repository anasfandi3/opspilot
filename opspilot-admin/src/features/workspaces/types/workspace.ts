export interface Workspace {
  id: number
  name: string
  slug: string
  owner_id: number
  role: string | null
  permissions: string[]
  created_at: string
  updated_at: string
}
