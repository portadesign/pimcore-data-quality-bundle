import React from 'react'
import { type AbstractModule, container } from '@pimcore/studio-ui-bundle'
import { serviceIds } from '@pimcore/studio-ui-bundle/app'
import { componentConfig, type ComponentRegistry } from '@pimcore/studio-ui-bundle/modules/app'
import { WorkflowTabWithBlockers } from '../components/workflow-tab-with-blockers'

export const WorkflowBlockersExtension: AbstractModule = {
  onInit (): void {
    const componentRegistry = container.get<ComponentRegistry>(serviceIds['App/ComponentRegistry/ComponentRegistry'])
    const name = componentConfig.element.editor.tab.workflow.name
    const OriginalWorkflowTab = componentRegistry.get<Record<string, never>>(name) as React.ComponentType

    componentRegistry.override({
      name,
      component: () => <WorkflowTabWithBlockers OriginalWorkflowTab={OriginalWorkflowTab} />
    })
  }
}
