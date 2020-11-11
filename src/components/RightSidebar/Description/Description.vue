<!--
  - @copyright Copyright (c) 2020 Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @author Marco Ambrosini <marcoambrosini@pm.me>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
	<div class="description"
		:class="{'description--editing': editing}">
		<RichContentEditable
			ref="contenteditable"
			v-model="descriptionText"
			class="description__contenteditable"
			:auto-complete="()=>{}"
			:maxlength="maxLength"
			:contenteditable="editing && !loading"
			:placeholder="placeholder"
			@submit="handleSubmitDescription"
			@keydown.esc="handleCancelEditing" />
		<template v-if="!loading">
			<template v-if="editing">
				<button
					class="description__button"
					:aria-label="t('spreed','Cancel editing description')"
					@click="handleCancelEditing">
					<Close decorative
						:size="20" />
				</button>
				<button
					class="description__button primary"
					:aria-label="t('spreed','Submit conversation description')"
					:disabled="!canSubmit"
					@click="handleSubmitDescription">
					<Check decorative
						:size="20" />
				</button>
				<div v-if="showCountDown"
					v-tooltip.auto="countDownWarningText"
					class="counter"
					tabindex="0"
					aria-label="countDownWarningText">
					<span>{{ charactersCountDown }}</span>
				</div>
			</template>
			<button v-if="!editing"
				class="description__button"
				:aria-label="t('spreed','Edit conversation description')"
				@click="handleEditDescription">
				<Pencil decorative
					:size="20" />
			</button>
		</template>
		<div v-if="loading" class="icon-loading-small spinner" />
	</div>
</template>

<script>
import Pencil from 'vue-material-design-icons/Pencil'
import Check from 'vue-material-design-icons/Check'
import Close from 'vue-material-design-icons/Close'
import RichContentEditable from '@nextcloud/vue/dist/Components/RichContenteditable'
import Tooltip from '@nextcloud/vue/dist/Directives/Tooltip'

export default {
	name: 'Description',
	components: {
		Pencil,
		Check,
		Close,
		RichContentEditable,
	},

	directives: {
		Tooltip,
	},

	props: {
		/**
		 * The description (An editable paragraph just above the sidebar tabs)
		 */
		descriptionTitle: {
			type: String,
			default: t('spreed', 'Description'),
		},

		/**
		 * A paragraph below the title.
		 */
		description: {
			type: String,
			default: '',
		},

		/**
		 * Shows or hides the editing buttons.
		 */
		editable: {
			type: Boolean,
			default: false,
		},

		/**
		 * Toggles the description editing state on and off.
		 */
		editing: {
			type: Boolean,
			default: false,
		},

		/**
		 * Placeholder for the contenteditable element.
		 */
		placeholder: {
			type: String,
			default: '',
		},

		/**
		 * Toggles the loading state on and off.
		 */
		loading: {
			type: Boolean,
			default: false,
		},

		/**
		 * Maximum description length in characters
		 */
		maxLength: {
			type: Number,
			default: 500,
		},
	},

	data() {
		return {
			descriptionText: '',
		}
	},

	computed: {
		contenteditable() {
			return this.$refs.contenteditable.$refs.contenteditable
		},

		canSubmit() {
			return this.charactersCount <= this.maxLength && this.descriptionText !== this.description
		},

		charactersCount() {
			return this.descriptionText.length
		},

		charactersCountDown() {
			return this.maxLength - this.charactersCount
		},

		showCountDown() {
			return this.charactersCount >= this.maxLength - 20
		},

		countDownWarningText() {
			return t('spreed', 'The description must be less or equal to {maxLength} characters long. Your current text is {charactersCount} characters long.', {
				maxLength: this.maxLength,
				charactersCount: this.charactersCount,
			})
		},
	},

	watch: {
		// Each time the prop changes, reflect the changes in the value stored in this component
		description() {
			this.descriptionText = this.description
		},
		editing(newValue) {
			if (!newValue) {
				this.descriptionText = this.description
			}
		},
	},

	methods: {
		handleEditDescription() {
			this.$emit('update:editing', true)
			this.$nextTick(() => {
				// Focus and select the text in the description
				this.contenteditable.focus()
				document.execCommand('selectAll', false, null)

			})
		},

		handleSubmitDescription() {
			if (!this.canSubmit) {
				return
			}
			this.$emit('submit:description', this.descriptionText)
		},

		handleCancelEditing() {
			this.descriptionText = this.description
			this.$emit('update:editing', false)
			// Deselect all the text that's been selected in `handleEditDescription`
			window.getSelection().removeAllRanges()
		},
	},
}
</script>

<style lang="scss" scoped>
@import '../../../assets/variables.scss';

.description {
	margin: 0 10px 10px 10px;
	display: flex;
	width: 100%;
	align-items: flex-end;
	position: relative;
	&--editing {
		box-shadow: 0 2px var(--color-primary-element);
		transition: all 150ms ease-in-out;
	}
	&__header {
		display: flex;
		align-items: center;
		justify-content: space-between;
		height: 44px;
	}
	&__title {
		color: var(--color-primary);
		font-weight: bold;
		font-size: var(--default-font-size);
		line-height: var(----default-line-height);
	}

	&__contenteditable {
		width: 100%;
		&--empty:before {
			position: absolute;
			content: attr(placeholder);
			color: var(--color-text-maxcontrast);
		}
	}
	&__buttons{
		display: flex;
		margin-top: 8px;
		justify-content: flex-end;
	}
	&__button {
		width: $clickable-area;
		height: $clickable-area;
		flex-shrink: 0;
		border: 0;
		padding: 0;
		margin: 0 0 4px 4px;
		&:not(.primary) {
			background-color: transparent;
		}

		&:hover,
		&:focus {
			background-color: var(--color-background-hover);
		}
		&:disabled {
			&:hover {
				background-color: var(--color-primary-element);
			}
		}
	}
}

.spinner {
	width: $clickable-area;
	height: $clickable-area;
	margin: 0 0 4px 0;
}

.counter {
	background-color: var(--color-background-dark);
	height: 44px;
	width: 44px;
	border-radius: var(--border-radius-pill);
	position: absolute;
	top: 0;
	right: 0px;
	display: flex;
	align-items: center;
	justify-content: center;
}

// Restyle richContentEditable component from our library.
::v-deep .rich-contenteditable__input {
	min-height: var(--default-line-height);
	border-radius: 0;
	overflow-x: hidden;
	padding: 0 0 4px 0;
	overflow-y: scroll;
	width: 100%;
	background-color: transparent;
	border: none;
	color: var(--color-main-text);
	font-size: var(--default-font-size);
	line-height: var(--default-line-height);
	&[contenteditable='false'] {
		background-color: transparent;
		color: var(--color-main-text);
		border: 0;
		opacity: 1;
		border-radius: 0;
	}
}
</style>
