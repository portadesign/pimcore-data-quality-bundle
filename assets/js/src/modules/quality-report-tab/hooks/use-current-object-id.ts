import { useGlobalElementContext } from '@pimcore/studio-ui-bundle/modules/element'

/**
 * This tab is only ever registered against the object editor's ObjectTabManager (see
 * quality-report-mount-extension.ts), so the global element context here is always the
 * data-object one — never asset/document. Mirrors the AI bundle's use-current-element.ts, but
 * narrowed to just the id since that's all this tab needs.
 */
export const useCurrentObjectId = (): number | null => {
  const { context } = useGlobalElementContext()

  if (context === undefined) {
    return null
  }

  return context.config.id
}
