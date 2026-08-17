import React from 'react'
import { Alert, Card, Empty, List, Progress, Space, Spin, Statistic, Tag, Typography } from 'antd'
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

const FailingChecks = ({ checks }: { checks: QualityCheck[] }): React.JSX.Element | null => {
  const failing = checks.filter((check) => !check.satisfied)

  if (failing.length === 0) {
    return null
  }

  return (
    <List
      size='small'
      dataSource={failing}
      renderItem={(check) => (
        <List.Item>
          <Space direction='vertical' size={0} style={{ width: '100%' }}>
            <Space>
              <Tag color={check.level === 'mandatory' ? 'red' : 'orange'}>{check.level}</Tag>
              <Typography.Text strong>{check.ruleName}</Typography.Text>
            </Space>
            {check.message !== null && (
              <Typography.Text type='secondary'>{check.message}</Typography.Text>
            )}
          </Space>
        </List.Item>
      )}
    />
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
      <FailingChecks checks={result.checks} />
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
