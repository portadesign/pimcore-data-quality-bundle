import React from 'react'
import { Alert, Space, Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { useElementContext, useElementDraft, useWorkflow } from '@pimcore/studio-ui-bundle/modules/element'
import { type BlockedTransition, QUALITY_GATE_BLOCKER_CODE, type TransitionBlocker } from '../types'

const QUALITY_REPORT_TAB_KEY = 'quality-report'

const blockedTransitionsOf = (item: { additionalAttributes?: Record<string, unknown> }): BlockedTransition[] =>
  (item.additionalAttributes?.blockedTransitions as BlockedTransition[] | undefined) ?? []

const BlockerLine = ({ blocker, onOpenQualityReport }: { blocker: TransitionBlocker, onOpenQualityReport: () => void }): React.JSX.Element => {
  const { t } = useTranslation()
  const failedChecks = blocker.parameters.failedChecks ?? []

  if (blocker.code !== QUALITY_GATE_BLOCKER_CODE || failedChecks.length === 0) {
    return <li>{blocker.message}</li>
  }

  const fields = failedChecks.map((check) => check.label !== '' ? check.label : check.ruleName).join(', ')

  return (
    <li>
      {t('portadesign_data_quality.workflow.missing', { fields })}{' '}
      <Typography.Link onClick={onOpenQualityReport}>{t('portadesign_data_quality.workflow.open_report')}</Typography.Link>
    </li>
  )
}

export const BlockedTransitions = (): React.JSX.Element | null => {
  const { t } = useTranslation()
  const { workflowDetailsData } = useWorkflow()
  const { id, elementType } = useElementContext()
  const { setActiveTab } = useElementDraft(id, elementType)
  const blocked = (workflowDetailsData?.items ?? []).reduce<BlockedTransition[]>(
    (acc, item) => [...acc, ...blockedTransitionsOf(item)],
    []
  )

  if (blocked.length === 0) {
    return null
  }

  const openQualityReport = (): void => {
    setActiveTab(QUALITY_REPORT_TAB_KEY)
  }

  return (
    <div style={{ padding: '0 16px 16px' }}>
      <Typography.Title level={5}>{t('portadesign_data_quality.workflow.blocked_title')}</Typography.Title>
      <Space direction='vertical' style={{ width: '100%' }}>
        {blocked.map((transition: BlockedTransition) => (
          <Alert
            key={transition.name}
            type='warning'
            showIcon
            message={transition.label}
            description={
              <ul style={{ margin: 0, paddingLeft: 16 }}>
                {transition.blockers.map((blocker: TransitionBlocker, index: number) => (
                  <BlockerLine key={index} blocker={blocker} onOpenQualityReport={openQualityReport} />
                ))}
              </ul>
            }
          />
        ))}
      </Space>
    </div>
  )
}
