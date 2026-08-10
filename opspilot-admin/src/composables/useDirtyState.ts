import { computed, onBeforeUnmount, ref, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { onBeforeRouteLeave } from 'vue-router'

export function dirtySnapshot(value: unknown) {
  return JSON.stringify(value)
}

export function shouldAllowDirtyNavigation(
  dirty: boolean,
  forced: boolean,
  confirmDiscard: () => boolean,
) {
  return forced || !dirty || confirmDiscard()
}

export function useDirtyState(value: MaybeRefOrGetter<unknown>) {
  const baseline = ref('')
  const forcedDiscard = ref(false)
  const snapshot = computed(() => dirtySnapshot(toValue(value)))
  const dirty = computed(() => baseline.value !== '' && snapshot.value !== baseline.value)
  function markClean() {
    baseline.value = snapshot.value
  }
  function forceDiscard() {
    forcedDiscard.value = true
    baseline.value = snapshot.value
  }
  watch(snapshot, (current) => !baseline.value && (baseline.value = current), { immediate: true })
  function beforeUnload(event: BeforeUnloadEvent) {
    if (!dirty.value) return
    event.preventDefault()
  }
  window.addEventListener('beforeunload', beforeUnload)
  onBeforeUnmount(() => window.removeEventListener('beforeunload', beforeUnload))
  onBeforeRouteLeave(() => {
    const forced = forcedDiscard.value
    forcedDiscard.value = false
    return shouldAllowDirtyNavigation(dirty.value, forced, () =>
      window.confirm('Discard your unsaved changes?'),
    )
  })
  return { dirty, markClean, forceDiscard }
}
