import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import { h, nextTick } from 'vue'
import ConfirmDialog from '@/components/app/ConfirmDialog.vue'

describe('ConfirmDialog', () => {
  it('emits confirmation from the generic action', async () => {
    const wrapper = mount(ConfirmDialog, {
      props: { title: 'Confirm', description: 'Continue?' },
      slots: { trigger: () => h('button', 'Open') },
      attachTo: document.body,
    })
    await wrapper.get('button').trigger('click')
    await nextTick()
    const action = [...document.body.querySelectorAll('button')].find(
      (button) => button.textContent?.trim() === 'Confirm',
    )
    expect(action).not.toBeNull()
    await action?.click()
    expect(wrapper.emitted('confirm')).toHaveLength(1)
  })
})
