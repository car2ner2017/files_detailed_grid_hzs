/**
 * SPDX-FileCopyrightText: 2026 Jakonda <car2ner2017@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'

export interface UserModalData {
	uid: string
	displayName: string
	email?: string
}

let activeModal: HTMLElement | null = null

export function showUserModal(user: UserModalData): void {
	if (activeModal) {
		closeUserModal()
	}

	const backdrop = document.createElement('div')
	backdrop.className = 'files-detailed-grid-modal-backdrop'

	const modal = document.createElement('div')
	modal.className = 'files-detailed-grid-modal'
	modal.setAttribute('role', 'dialog')
	modal.setAttribute('aria-modal', 'true')
	modal.setAttribute('aria-labelledby', 'files-detailed-grid-modal-title')

	// Close button
	const closeButton = document.createElement('button')
	closeButton.className = 'files-detailed-grid-modal-close'
	closeButton.setAttribute('aria-label', t('files_detailed_grid_hzs', 'Close'))
	closeButton.innerHTML = '&times;'
	closeButton.onclick = (e) => {
		e.stopPropagation()
		closeUserModal()
	}

	// Modal Header
	const header = document.createElement('div')
	header.className = 'files-detailed-grid-modal-header'

	const title = document.createElement('h3')
	title.id = 'files-detailed-grid-modal-title'
	title.textContent = t('files_detailed_grid_hzs', 'User information')
	header.appendChild(title)
	header.appendChild(closeButton)

	// Modal Body
	const body = document.createElement('div')
	body.className = 'files-detailed-grid-modal-body'

	// Avatar & Name row
	const userRow = document.createElement('div')
	userRow.className = 'files-detailed-grid-modal-user'

	const avatar = document.createElement('img')
	avatar.className = 'files-detailed-grid-modal-avatar'
	avatar.src = generateUrl('/avatar/{user}/{size}', { user: user.uid, size: 64 })
	avatar.alt = user.displayName
	avatar.onerror = () => {
		avatar.style.display = 'none'
	}

	const nameContainer = document.createElement('div')
	nameContainer.className = 'files-detailed-grid-modal-names'

	const displayName = document.createElement('div')
	displayName.className = 'files-detailed-grid-modal-displayname'
	displayName.textContent = user.displayName

	const uid = document.createElement('div')
	uid.className = 'files-detailed-grid-modal-uid'
	uid.textContent = `@${user.uid}`

	nameContainer.appendChild(displayName)
	nameContainer.appendChild(uid)

	userRow.appendChild(avatar)
	userRow.appendChild(nameContainer)
	body.appendChild(userRow)

	// Email section
	const emailRow = document.createElement('div')
	emailRow.className = 'files-detailed-grid-modal-email-row'

	const emailLabel = document.createElement('span')
	emailLabel.className = 'files-detailed-grid-modal-label'
	emailLabel.textContent = t('files_detailed_grid_hzs', 'Email') + ':'

	const emailValue = document.createElement('span')
	emailValue.className = 'files-detailed-grid-modal-email-value'
	emailValue.textContent = user.email ? user.email : t('files_detailed_grid_hzs', 'Not specified')

	emailRow.appendChild(emailLabel)
	emailRow.appendChild(emailValue)

	if (user.email) {
		const copyButton = document.createElement('button')
		copyButton.className = 'files-detailed-grid-modal-copy-btn'
		copyButton.title = t('files_detailed_grid_hzs', 'Copy email')
		copyButton.textContent = t('files_detailed_grid_hzs', 'Copy')
		copyButton.onclick = async (e) => {
			e.stopPropagation()
			try {
				await navigator.clipboard.writeText(user.email!)
				copyButton.textContent = t('files_detailed_grid_hzs', 'Copied!')
				setTimeout(() => {
					copyButton.textContent = t('files_detailed_grid_hzs', 'Copy')
				}, 2000)
			} catch {
				// Fallback
				const input = document.createElement('input')
				input.value = user.email!
				document.body.appendChild(input)
				input.select()
				document.execCommand('copy')
				document.body.removeChild(input)
				copyButton.textContent = t('files_detailed_grid_hzs', 'Copied!')
				setTimeout(() => {
					copyButton.textContent = t('files_detailed_grid_hzs', 'Copy')
				}, 2000)
			}
		}
		emailRow.appendChild(copyButton)
	}

	body.appendChild(emailRow)

	// Modal Footer / Actions
	const footer = document.createElement('div')
	footer.className = 'files-detailed-grid-modal-footer'

	const profileLink = document.createElement('a')
	profileLink.className = 'files-detailed-grid-modal-profile-link'
	profileLink.href = generateUrl('/u/{user}', { user: user.uid })
	profileLink.textContent = t('files_detailed_grid_hzs', 'View full profile')
	profileLink.target = '_blank'
	profileLink.rel = 'noreferrer noopener'

	const closeBtn = document.createElement('button')
	closeBtn.className = 'files-detailed-grid-modal-btn-close'
	closeBtn.textContent = t('files_detailed_grid_hzs', 'Close')
	closeBtn.onclick = (e) => {
		e.stopPropagation()
		closeUserModal()
	}

	footer.appendChild(profileLink)
	footer.appendChild(closeBtn)

	modal.appendChild(header)
	modal.appendChild(body)
	modal.appendChild(footer)
	backdrop.appendChild(modal)

	// Events
	backdrop.onclick = (e) => {
		if (e.target === backdrop) {
			closeUserModal()
		}
	}

	const onKeyDown = (e: KeyboardEvent) => {
		if (e.key === 'Escape') {
			closeUserModal()
		}
	}
	document.addEventListener('keydown', onKeyDown)
	;(backdrop as any)._cleanupKeyDown = () => {
		document.removeEventListener('keydown', onKeyDown)
	}

	document.body.appendChild(backdrop)
	activeModal = backdrop
}

export function closeUserModal(): void {
	if (activeModal) {
		if ((activeModal as any)._cleanupKeyDown) {
			(activeModal as any)._cleanupKeyDown()
		}
		activeModal.remove()
		activeModal = null
	}
}

