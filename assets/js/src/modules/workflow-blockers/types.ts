export const QUALITY_GATE_BLOCKER_CODE = 'dq_gate'

export interface BlockedCheck {
  ruleId: string
  ruleName: string
  label: string
}

export interface TransitionBlocker {
  code: string
  message: string
  parameters: {
    failedChecks?: BlockedCheck[]
  }
}

export interface BlockedTransition {
  name: string
  label: string
  blockers: TransitionBlocker[]
}
