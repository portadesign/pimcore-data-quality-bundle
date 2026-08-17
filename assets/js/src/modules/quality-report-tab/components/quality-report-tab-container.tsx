import React from 'react'
import { Empty } from 'antd'
import { useCurrentObjectId } from '../hooks/use-current-object-id'
import { QualityReportTab } from './quality-report-tab'

export const QualityReportTabContainer = (): React.JSX.Element => {
  const objectId = useCurrentObjectId()

  if (objectId === null) {
    return <Empty description='No object selected' />
  }

  return <QualityReportTab objectId={objectId} />
}
