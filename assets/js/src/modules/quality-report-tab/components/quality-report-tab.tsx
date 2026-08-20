import React from 'react'
import { Alert, Card, Empty, Progress, Space, Spin, Statistic, Tooltip, Typography } from 'antd'
import { useQualityReport } from '../hooks/use-quality-report'
import { type ChannelQualityResult, type CategoryQualityResult, type QualityCheck, type QualityResultDto } from '../types'

export interface QualityReportTabProps {
  objectId: number
}

const progressStatus = (result: QualityResultDto): 'success' | 'exception' | 'active' => {
  if (!result.mandatoryComplete) {
    return 'exception'
  }
  return result.score >= 100 ? 'success' : 'active'
}

type LightColor = 'red' | 'gold' | 'green'

// Green whenever satisfied, regardless of level - a satisfied optional check is just as "done" as
// a satisfied mandatory one. Only the failure severity differs: an unsatisfied mandatory check
// blocks readiness (red), while unsatisfied recommended/optional checks are advisory (yellow).
const checkColor = (check: QualityCheck): LightColor => {
  if (check.satisfied) {
    return 'green'
  }
  return check.level === 'mandatory' ? 'red' : 'gold'
}

const colorHex: Record<LightColor, string> = {
  red: '#f5222d',
  gold: '#faad14',
  green: '#52c41a'
}

const colorRank: Record<LightColor, number> = { red: 0, gold: 1, green: 2 }

// Per-field traffic light grid: one chip per rule, colored red/yellow/green, sorted worst-first so
// the fields needing attention are immediately visible without reading a list of prose. Labeled by
// targetKey (the actual object/Classification-Store field the rule checks) when available, falling
// back to the rule's own description for unscoped or legacy rules with no targetKey - the full
// rule name + message is always available on hover via the tooltip.
const FieldTrafficLights = ({ checks }: { checks: QualityCheck[] }): React.JSX.Element | null => {
  if (checks.length === 0) {
    return null
  }

  const sorted = [...checks].sort((a, b) => colorRank[checkColor(a)] - colorRank[checkColor(b)])

  return (
    <div style={{ display: 'flex', flexWrap: 'wrap', gap: 8 }}>
      {sorted.map((check) => {
        const color = checkColor(check)
        const label = check.targetKey ?? check.ruleName

        return (
          <Tooltip
            key={check.ruleId}
            title={
              <Space direction='vertical' size={0}>
                <Typography.Text strong style={{ color: 'inherit' }}>{check.ruleName}</Typography.Text>
                <Typography.Text style={{ color: 'inherit' }}>{check.level} · weight {check.weight}</Typography.Text>
                {check.message !== null && <Typography.Text style={{ color: 'inherit' }}>{check.message}</Typography.Text>}
              </Space>
            }
          >
            <div
              style={{
                display: 'flex',
                alignItems: 'center',
                gap: 6,
                padding: '4px 10px',
                borderRadius: 14,
                border: `1px solid ${colorHex[color]}`,
                backgroundColor: `color-mix(in srgb, ${colorHex[color]} 12%, transparent)`,
                cursor: 'default'
              }}
            >
              <span
                style={{
                  display: 'inline-block',
                  width: 9,
                  height: 9,
                  borderRadius: '50%',
                  backgroundColor: colorHex[color],
                  flexShrink: 0
                }}
              />
              <Typography.Text style={{ fontSize: 12 }}>{label}</Typography.Text>
            </div>
          </Tooltip>
        )
      })}
    </div>
  )
}

const ScopeResultCard = ({ title, result }: { title: string, result: QualityResultDto }): React.JSX.Element => (
  <Card size='small' title={title} style={{ marginBottom: 12 }}>
    <Space direction='vertical' size='middle' style={{ width: '100%' }}>
      <Space size='large' align='center'>
        <Progress type='circle' size={64} percent={result.score} status={progressStatus(result)} />
        <Statistic
          title='Mandatory rules'
          value={result.mandatoryComplete ? 'Complete' : 'Incomplete'}
          valueStyle={{ color: result.mandatoryComplete ? '#3f8600' : '#cf1322' }}
        />
      </Space>
      <FieldTrafficLights checks={result.checks} />
    </Space>
  </Card>
)

export const QualityReportTab = ({ objectId }: QualityReportTabProps): React.JSX.Element => {
  const { data, loading, error, refetch } = useQualityReport(objectId)

  if (loading && data === null) {
    return (
      <Space direction='vertical' align='center' style={{ width: '100%', padding: 48 }}>
        <Spin />
      </Space>
    )
  }

  if (error !== null) {
    return (
      <Alert
        message='Failed to load quality report'
        description={error}
        type='error'
        showIcon
        action={<Typography.Link onClick={refetch}>Retry</Typography.Link>}
      />
    )
  }

  if (data === null) {
    return <Empty description='No quality report available' />
  }

  const hasScopes = data.byChannel.length > 0 || data.byCategory.length > 0

  return (
    <Space direction='vertical' size='large' style={{ width: '100%' }}>
      <ScopeResultCard title='Overall' result={data.overall} />

      {!hasScopes && (
        <Empty description='This product is not assigned to any channel or category' />
      )}

      {data.byChannel.length > 0 && (
        <div>
          <Typography.Title level={5}>By channel</Typography.Title>
          {data.byChannel.map((channel: ChannelQualityResult) => (
            <ScopeResultCard key={channel.channelId} title={channel.channelName} result={channel} />
          ))}
        </div>
      )}

      {data.byCategory.length > 0 && (
        <div>
          <Typography.Title level={5}>By category</Typography.Title>
          {data.byCategory.map((category: CategoryQualityResult) => (
            <ScopeResultCard key={category.categoryId} title={category.categoryName} result={category} />
          ))}
        </div>
      )}
    </Space>
  )
}
