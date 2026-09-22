import { type IAbstractPlugin } from '@pimcore/studio-ui-bundle'
import { WorkflowBlockersExtension } from './modules/workflow-blockers-extension'

export const WorkflowBlockersPlugin: IAbstractPlugin = {
  name: 'WorkflowBlockersPlugin',

  onStartup ({ moduleSystem }) {
    moduleSystem.registerModule(WorkflowBlockersExtension)
  }
}
