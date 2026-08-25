import React from 'react'
import { type AbstractModule, container } from '@pimcore/studio-ui-bundle'
import { serviceIds } from '@pimcore/studio-ui-bundle/app'
import { type ObjectTabManager } from '@pimcore/studio-ui-bundle/modules/data-object'
import { Icon } from '@pimcore/studio-ui-bundle/components'
import { QualityReportTabContainer } from '../components/quality-report-tab-container'

/**
 * Registered as a real editor tab (not a WidgetRegistry side-panel — see the AI bundle's
 * ai-assistant-mount-extension.tsx for that pattern, used there for an unrelated reason)
 * against ObjectTabManager, Pimcore Studio's own tab manager for the object editor. Studio's
 * native tabs (edit/versions/preview/…) are registered the exact same way from
 * vendor/pimcore/studio-ui-bundle's own
 * core/modules/data-object/editor/types/object/index.tsx — this mirrors that.
 *
 * 2026-08-13: this registration was briefly suspected of breaking Studio's Product editor
 * ("TypeError: t.map is not a function" on every Product open) and was temporarily disabled.
 * Confirmed via live browser reproduction that this tab was NOT the cause: the crash persisted
 * with this registration fully disabled. The actual root cause was in the HOST APP
 * (pimcore-playground/var/classes/definition_Product.php) — the `Layout` panel's `children`
 * array had non-sequential PHP keys (13, 14 inserted before the existing 6), which made
 * `json_encode` serialize it as a JSON object instead of an array, and Studio's Edit-tab
 * renderer does `layout.children.map(...)`. Fixed by re-sequencing the keys; re-enabled here
 * after confirming the fix live (product 496 opens cleanly, including this tab).
 */
export const QualityReportMountExtension: AbstractModule = {
  onInit (): void {
    const objectTabManager = container.get<ObjectTabManager>(serviceIds['DataObject/Editor/ObjectTabManager'])

    objectTabManager.register({
      key: 'quality-report',
      label: 'Data Quality',
      icon: <Icon value='data-quality' />,
      children: <QualityReportTabContainer />,
      userPermission: 'portadesign_data_quality_report',
      // This tab only makes sense for Product objects — the quality scoring engine and the
      // /objects/{id}/report endpoint are both hard-scoped to Product. `className` here mirrors
      // the field read by Studio UI's own use-element-actions-menu.tsx
      // (`'className' in element && element.className`) for the data-object case of the shared
      // element-editor tab-manager's `hidden` predicate — confirmed against the DataObject type
      // in data-object-api-slice.gen.ts (`className: string`), and cast defensively here since
      // IElementDraft itself doesn't statically declare it.
      hidden: (element): boolean => {
        const className = (element as unknown as { className?: string }).className

        return className !== 'Product'
      }
    })
  }
}
