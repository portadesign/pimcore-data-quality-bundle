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

export interface QualityReport {
  overall: QualityResultDto
  byChannel: ChannelQualityResult[]
  byCategory: CategoryQualityResult[]
  gates: GateResult[]
}
