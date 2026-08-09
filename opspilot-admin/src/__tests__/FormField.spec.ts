import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import FormField from '@/components/app/forms/FormField.vue'

describe('FormField', () => {
  it('associates descriptions and errors with its control', () => {
    const wrapper = mount(FormField, {
      props: {
        id: 'email',
        label: 'Email',
        description: 'Work address',
        error: 'Invalid email',
        required: true,
      },
      slots: { default: '<input id="email" />' },
    })
    expect(wrapper.get('label').attributes('for')).toBe('email')
    expect(wrapper.text()).toContain('Work address')
    expect(wrapper.get('[role="alert"]').text()).toBe('Invalid email')
    expect(wrapper.text()).toContain('required')
  })
})
