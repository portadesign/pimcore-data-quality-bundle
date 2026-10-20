export interface CheckGate {
  workflow: string
  transition: string
  label: string
  blocking: boolean
}

export interface QualityCheck {
  ruleId: string
  ruleName: string
  satisfied: boolean
  level: string
  weight: number
  targetKey: string | null
  label: string
  detail?: string | null
  gates?: CheckGate[]
}

export interface GateResult {
  workflow: string
  transition: string
  label: string
  requiredLevel: string
  passed: boolean
  failedChecks: QualityCheck[]
}

export interface QualityResultDto {
  score: number
  mandatoryComplete: boolean
  configured?: boolean
  channelId: number | null
  categoryId: number | null
  checks: QualityCheck[]
}

export interface ChannelQualityResult extends QualityResultDto {
  channelId: number
  channelName: string
}

export interface CategoryQualityResult extends QualityResultDto {
  categoryId: number
  categoryName: string
}

export type ProfileStatus = 'ready' | 'incomplete' | 'not_configured' | 'ambiguous' | 'error'

export interface ProfileReadinessResult {
  categoryId: number
  categoryName: string
  channelId: number
  channelName: string
  status: ProfileStatus
  profileId: number | null
  profileName: string | null
  score: number | null
  mandatoryComplete: boolean
  configured: boolean
  checks: QualityCheck[]
}

export interface QualityReport {
  overall: QualityResultDto
  byChannel: ChannelQualityResult[]
  byCategory: CategoryQualityResult[]
  byProfile?: ProfileReadinessResult[]
  gates: GateResult[]
}
