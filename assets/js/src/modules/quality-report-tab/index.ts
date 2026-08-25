import { type IAbstractPlugin } from '@pimcore/studio-ui-bundle'
import { QualityReportMountExtension } from './modules/quality-report-mount-extension'

export const QualityReportTabPlugin: IAbstractPlugin = {
  name: 'QualityReportTabPlugin',

  onStartup ({ moduleSystem }) {
    moduleSystem.registerModule(QualityReportMountExtension)
  }
}
