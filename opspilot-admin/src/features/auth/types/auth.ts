export interface AuthUser {
  id: number
  name: string
  email: string
  created_at: string
  updated_at: string
}
export interface LoginCredentials {
  email: string
  password: string
}
