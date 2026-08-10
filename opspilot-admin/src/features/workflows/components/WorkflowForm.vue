<script setup lang="ts">
import { computed } from 'vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  createDraftCondition,
  createDraftStep,
  moveStep,
  normalizeApprover,
  specificApproverAvailability,
  specificApproverOptions,
} from '../workflowForm'
import {
  datetimeEditorValue,
  defaultConditionValue,
  normalizeDatetimeEditorInput,
  normalizeNumericConditionInput,
  operatorLabels,
  operatorsForField,
} from '../conditions'
import StringListEditor from './StringListEditor.vue'
import type {
  RequestType,
  RequestTypeField,
  SelectOption,
} from '@/features/request-types/types/requestType'
import type { WorkspaceMember } from '@/features/members/types/member'
import type {
  DraftCondition,
  WorkflowConditionOperator,
  WorkflowFormModel,
} from '../types/workflow'

const props = defineProps<{
  modelValue: WorkflowFormModel
  requestTypes: RequestType[]
  members: WorkspaceMember[]
  membersAvailable: boolean
  errors: Record<string, string[]>
  generalError?: string
  saving?: boolean
  editing?: boolean
}>()
const emit = defineEmits<{
  'update:modelValue': [value: WorkflowFormModel]
  submit: []
  structural: []
}>()
const form = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value),
})
const selectedType = computed(() =>
  props.requestTypes.find((type) => type.id === form.value.request_type_id),
)
const availableMembers = computed(() => specificApproverOptions(props.members))
const error = (path: string) => props.errors[path]?.[0] ?? ''
const options = (field?: RequestTypeField) =>
  (field?.config as { options?: SelectOption[] } | null)?.options ?? []
function fieldFor(condition: DraftCondition) {
  return selectedType.value?.fields.find((field) => field.id === condition.field_id)
}
function selectRequestType(value: string) {
  form.value.request_type_id = Number(value) || null
  form.value.steps.forEach((step) => {
    step.conditions = []
  })
  emit('structural')
}
function addStep() {
  form.value.steps.push(createDraftStep())
  emit('structural')
}
function removeStep(index: number) {
  form.value.steps.splice(index, 1)
  emit('structural')
}
function reorder(index: number, to: number) {
  moveStep(form.value.steps, index, to)
  emit('structural')
}
function changeApprover(index: number, value: string) {
  form.value.steps[index] = normalizeApprover(form.value.steps[index]!, value as 'role' | 'user')
  emit('structural')
}
function addCondition(stepIndex: number) {
  form.value.steps[stepIndex]!.conditions.push(createDraftCondition(selectedType.value?.fields[0]))
  emit('structural')
}
function removeCondition(stepIndex: number, index: number) {
  form.value.steps[stepIndex]!.conditions.splice(index, 1)
  emit('structural')
}
function changeField(condition: DraftCondition, value: string) {
  const field = selectedType.value?.fields.find((item) => item.id === Number(value))
  if (!field) return
  condition.field_id = field.id
  condition.operator = operatorsForField(field)[0]!
  condition.value = defaultConditionValue(field, condition.operator)
  emit('structural')
}
function changeOperator(condition: DraftCondition, value: string) {
  const field = fieldFor(condition)
  if (!field) return
  condition.operator = value as WorkflowConditionOperator
  condition.value = defaultConditionValue(field, condition.operator)
  emit('structural')
}
function toggleSetValue(condition: DraftCondition, value: string, checked: boolean) {
  const current = Array.isArray(condition.value) ? condition.value : []
  condition.value = checked ? [...current, value] : current.filter((item) => item !== value)
}
function updateNumericValue(condition: DraftCondition, value: string | number) {
  condition.value = normalizeNumericConditionInput(value)
}
function updateDatetimeValue(condition: DraftCondition, value: string | number) {
  condition.value = normalizeDatetimeEditorInput(String(value), condition.value)
}
</script>
<template>
  <form class="space-y-8" @submit.prevent="emit('submit')">
    <div
      v-if="generalError"
      role="alert"
      class="rounded-lg border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive"
    >
      {{ generalError }}
    </div>
    <Card
      ><CardHeader><CardTitle>Workflow metadata</CardTitle></CardHeader
      ><CardContent class="grid gap-5 md:grid-cols-2">
        <div>
          <label for="workflow-request-type" class="text-sm font-medium">Request type</label
          ><select
            id="workflow-request-type"
            class="mt-2 h-10 w-full rounded-md border bg-background px-3 text-sm"
            :disabled="editing"
            :value="form.request_type_id ?? ''"
            @change="selectRequestType(($event.target as HTMLSelectElement).value)"
          >
            <option value="">Select request type</option>
            <option v-for="type in requestTypes" :key="type.id" :value="type.id">
              {{ type.name }}
            </option>
          </select>
          <p v-if="error('request_type_id')" class="mt-1 text-sm text-destructive">
            {{ error('request_type_id') }}
          </p>
        </div>
        <div>
          <label for="workflow-name" class="text-sm font-medium">Name</label
          ><Input id="workflow-name" v-model="form.name" class="mt-2" />
          <p v-if="error('name')" class="mt-1 text-sm text-destructive">{{ error('name') }}</p>
        </div>
        <div class="md:col-span-2">
          <label for="workflow-description" class="text-sm font-medium">Description</label
          ><Textarea id="workflow-description" v-model="form.description" class="mt-2" />
        </div> </CardContent
    ></Card>
    <section class="space-y-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-xl font-semibold">Sequential approval steps</h2>
          <p class="text-sm text-muted-foreground">
            Steps run in the order shown when their conditions apply.
          </p>
        </div>
        <Button type="button" variant="outline" @click="addStep">Add step</Button>
      </div>
      <p
        v-if="!form.steps.length"
        class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
      >
        No steps yet. Add at least one step before publishing.
      </p>
      <Card v-for="(step, index) in form.steps" :key="step.clientId"
        ><CardHeader class="gap-3 sm:flex-row sm:items-center sm:justify-between"
          ><CardTitle class="text-base">Step {{ index + 1 }}</CardTitle>
          <div class="flex flex-wrap gap-2">
            <Button
              type="button"
              size="sm"
              variant="outline"
              :disabled="index === 0"
              :aria-label="`Move step ${index + 1} up`"
              @click="reorder(index, index - 1)"
              >Move up</Button
            ><Button
              type="button"
              size="sm"
              variant="outline"
              :disabled="index === form.steps.length - 1"
              :aria-label="`Move step ${index + 1} down`"
              @click="reorder(index, index + 1)"
              >Move down</Button
            ><Button
              type="button"
              size="sm"
              variant="ghost"
              :aria-label="`Remove step ${index + 1}`"
              @click="removeStep(index)"
              >Remove</Button
            >
          </div></CardHeader
        ><CardContent class="space-y-5">
          <div>
            <label :for="`step-${index}-name`" class="text-sm font-medium">Step name</label
            ><Input :id="`step-${index}-name`" v-model="step.name" class="mt-2" />
            <p v-if="error(`steps.${index}.name`)" class="mt-1 text-sm text-destructive">
              {{ error(`steps.${index}.name`) }}
            </p>
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div>
              <label :for="`step-${index}-type`" class="text-sm font-medium">Approver type</label
              ><select
                :id="`step-${index}-type`"
                class="mt-2 h-10 w-full rounded-md border bg-background px-3 text-sm"
                :value="step.approver_type"
                @change="changeApprover(index, ($event.target as HTMLSelectElement).value)"
              >
                <option value="role">Workspace role</option>
                <option value="user">Specific user</option>
              </select>
            </div>
            <div v-if="step.approver_type === 'role'">
              <label :for="`step-${index}-role`" class="text-sm font-medium">Approver role</label
              ><select
                :id="`step-${index}-role`"
                v-model="step.approver_role"
                class="mt-2 h-10 w-full rounded-md border bg-background px-3 text-sm"
              >
                <option value="owner">Owner</option>
                <option value="admin">Admin</option>
                <option value="approver">Approver</option>
              </select>
              <p v-if="error(`steps.${index}.approver_role`)" class="mt-1 text-sm text-destructive">
                {{ error(`steps.${index}.approver_role`) }}
              </p>
            </div>
            <div v-else>
              <label :for="`step-${index}-user`" class="text-sm font-medium"
                >Specific approver</label
              ><select
                :id="`step-${index}-user`"
                v-model.number="step.approver_user_id"
                :disabled="!membersAvailable"
                class="mt-2 h-10 w-full rounded-md border bg-background px-3 text-sm"
              >
                <option :value="null">
                  {{ membersAvailable ? 'Select member' : 'Member list unavailable' }}
                </option>
                <option v-for="member in availableMembers" :key="member.id" :value="member.id">
                  {{ member.name }} ({{ member.email }})
                </option>
              </select>
              <p class="mt-1 text-xs text-muted-foreground">
                {{ specificApproverAvailability(membersAvailable) }}
              </p>
              <p
                v-if="error(`steps.${index}.approver_user_id`)"
                class="mt-1 text-sm text-destructive"
              >
                {{ error(`steps.${index}.approver_user_id`) }}
              </p>
            </div>
          </div>
          <div class="rounded-lg border bg-muted/20 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h3 class="font-medium">Conditions</h3>
                <p class="text-xs text-muted-foreground">
                  No conditions means this step always runs.
                </p>
              </div>
              <Button
                type="button"
                size="sm"
                variant="outline"
                :disabled="!selectedType?.fields.length"
                @click="addCondition(index)"
                >Add condition</Button
              >
            </div>
            <div v-if="step.conditions.length > 1" class="mt-4">
              <label :for="`step-${index}-logic`" class="text-sm font-medium">Run when</label
              ><select
                :id="`step-${index}-logic`"
                v-model="step.condition_logic"
                class="ml-2 h-9 rounded-md border bg-background px-2 text-sm"
              >
                <option value="all">All conditions match</option>
                <option value="any">Any condition matches</option>
              </select>
            </div>
            <div
              v-for="(condition, conditionIndex) in step.conditions"
              :key="condition.clientId"
              class="mt-4 grid gap-3 rounded-md border bg-background p-3 lg:grid-cols-[1fr_1fr_1fr_auto]"
            >
              <div>
                <label
                  :for="`condition-${index}-${conditionIndex}-field`"
                  class="text-xs font-medium"
                  >Field</label
                ><select
                  :id="`condition-${index}-${conditionIndex}-field`"
                  class="mt-1 h-10 w-full rounded-md border bg-background px-2 text-sm"
                  :value="condition.field_id ?? ''"
                  @change="changeField(condition, ($event.target as HTMLSelectElement).value)"
                >
                  <option v-for="field in selectedType?.fields" :key="field.id" :value="field.id">
                    {{ field.label }}
                  </option>
                </select>
              </div>
              <div>
                <label
                  :for="`condition-${index}-${conditionIndex}-operator`"
                  class="text-xs font-medium"
                  >Operator</label
                ><select
                  :id="`condition-${index}-${conditionIndex}-operator`"
                  class="mt-1 h-10 w-full rounded-md border bg-background px-2 text-sm"
                  :value="condition.operator"
                  @change="changeOperator(condition, ($event.target as HTMLSelectElement).value)"
                >
                  <option
                    v-for="operator in operatorsForField(fieldFor(condition))"
                    :key="operator"
                    :value="operator"
                  >
                    {{ operatorLabels[operator] }}
                  </option>
                </select>
              </div>
              <div>
                <span class="text-xs font-medium">Value</span
                ><template v-if="fieldFor(condition)?.type === 'boolean'"
                  ><select
                    v-model="condition.value"
                    class="mt-1 h-10 w-full rounded-md border bg-background px-2 text-sm"
                    :aria-label="`Condition ${conditionIndex + 1} value`"
                  >
                    <option :value="true">Yes</option>
                    <option :value="false">No</option>
                  </select></template
                ><template
                  v-else-if="
                    ['select', 'multiselect'].includes(fieldFor(condition)?.type ?? '') &&
                    !['in', 'not_in'].includes(condition.operator)
                  "
                  ><select
                    v-model="condition.value"
                    class="mt-1 h-10 w-full rounded-md border bg-background px-2 text-sm"
                    :aria-label="`Condition ${conditionIndex + 1} value`"
                  >
                    <option
                      v-for="option in options(fieldFor(condition))"
                      :key="option.value"
                      :value="option.value"
                    >
                      {{ option.label }}
                    </option>
                  </select></template
                ><template
                  v-else-if="
                    fieldFor(condition)?.type === 'select' &&
                    ['in', 'not_in'].includes(condition.operator)
                  "
                  ><div class="mt-1 space-y-1">
                    <label
                      v-for="option in options(fieldFor(condition))"
                      :key="option.value"
                      class="flex items-center gap-2 text-sm"
                      ><input
                        type="checkbox"
                        :checked="
                          Array.isArray(condition.value) && condition.value.includes(option.value)
                        "
                        @change="
                          toggleSetValue(
                            condition,
                            option.value,
                            ($event.target as HTMLInputElement).checked,
                          )
                        "
                      />{{ option.label }}</label
                    >
                  </div></template
                ><StringListEditor
                  v-else-if="['in', 'not_in'].includes(condition.operator)"
                  :model-value="Array.isArray(condition.value) ? condition.value : []"
                  :label="`Condition ${conditionIndex + 1} value`"
                  @update:model-value="condition.value = $event"
                /><Input
                  v-else-if="['number', 'decimal'].includes(fieldFor(condition)?.type ?? '')"
                  type="number"
                  step="any"
                  :model-value="typeof condition.value === 'number' ? condition.value : ''"
                  class="mt-1"
                  :aria-label="`Condition ${conditionIndex + 1} value`"
                  @update:model-value="updateNumericValue(condition, $event)"
                /><Input
                  v-else-if="fieldFor(condition)?.type === 'datetime'"
                  type="datetime-local"
                  step="1"
                  :model-value="datetimeEditorValue(condition.value)"
                  class="mt-1"
                  :aria-label="`Condition ${conditionIndex + 1} value`"
                  @update:model-value="updateDatetimeValue(condition, $event)"
                /><Input
                  v-else
                  v-model="condition.value as string"
                  class="mt-1"
                  :type="fieldFor(condition)?.type === 'date' ? 'date' : 'text'"
                  :aria-label="`Condition ${conditionIndex + 1} value`"
                />
              </div>
              <Button
                type="button"
                size="sm"
                variant="ghost"
                class="self-end"
                :aria-label="`Remove condition ${conditionIndex + 1} from step ${index + 1}`"
                @click="removeCondition(index, conditionIndex)"
                >Remove</Button
              >
              <p
                v-if="error(`steps.${index}.conditions.${conditionIndex}.value`)"
                class="text-sm text-destructive lg:col-span-4"
              >
                {{ error(`steps.${index}.conditions.${conditionIndex}.value`) }}
              </p>
            </div>
          </div>
        </CardContent></Card
      >
    </section>
    <div class="flex justify-end">
      <Button type="submit" :disabled="saving">{{ saving ? 'Saving…' : 'Save workflow' }}</Button>
    </div>
  </form>
</template>
