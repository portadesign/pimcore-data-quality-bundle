import { useCallback, useEffect, useState } from 'react'
import { type QualityReport } from '../types'

export interface UseQualityReportResult {
  data: QualityReport | null
  loading: boolean
  error: string | null
  refetch: () => void
}

export const useQualityReport = (objectId: number): UseQualityReportResult => {
  const [data, setData] = useState<QualityReport | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [refetchToken, setRefetchToken] = useState(0)

  useEffect(() => {
    let cancelled = false
    setLoading(true)
    setError(null)

    fetch(`/pimcore-studio/api/quality-bundle/objects/${objectId}/report`, {
      credentials: 'include'
    })
      .then(async (response) => {
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`)
        }
        return (await response.json()) as QualityReport
      })
      .then((result) => {
        if (!cancelled) {
          setData(result)
        }
      })
      .catch((err: Error) => {
        if (!cancelled) {
          setError(err.message)
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false)
        }
      })

    return () => {
      cancelled = true
    }
  }, [objectId, refetchToken])

  const refetch = useCallback((): void => {
    setRefetchToken((token) => token + 1)
  }, [])

  return { data, loading, error, refetch }
}
