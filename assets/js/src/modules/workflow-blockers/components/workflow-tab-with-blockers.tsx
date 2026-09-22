import React from 'react'
import { BlockedTransitions } from './blocked-transitions'

export const WorkflowTabWithBlockers = ({ OriginalWorkflowTab }: { OriginalWorkflowTab: React.ComponentType }): React.JSX.Element => (
  <>
    <OriginalWorkflowTab />
    <BlockedTransitions />
  </>
)
