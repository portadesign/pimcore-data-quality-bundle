import React from 'react'
import { Empty } from 'antd'
import { useTranslation } from 'react-i18next'
import { useCurrentObjectId } from '../hooks/use-current-object-id'
import { QualityReportTab } from './quality-report-tab'

export const QualityReportTabContainer = (): React.JSX.Element => {
  const { t } = useTranslation()
  const objectId = useCurrentObjectId()

  if (objectId === null) {
    return <Empty description={t('portadesign_data_quality.report.no_object')} />
  }

  return <QualityReportTab objectId={objectId} />
}
