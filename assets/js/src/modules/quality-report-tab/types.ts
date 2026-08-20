export interface QualityCheck {
  ruleId: string
  ruleName: string
  satisfied: boolean
  level: string
  weight: number
  targetKey: string | null
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
}
