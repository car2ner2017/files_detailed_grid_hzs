/**
 * SPDX-FileCopyrightText: 2026 Jakonda <car2ner2017@gmail.com>
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import './files-init.css'
import {
	Column,
	FileListFilter,
	getFileListFilters,
	getFilesRegistry,
	getNavigation,
	registerFileListFilter,
	unregisterFileListFilter,
} from '@nextcloud/files'
import type { IFileListFilterWithUi } from '@nextcloud/files'
import { registerDavProperty } from '@nextcloud/files/dav'
import { formatRelativeTime, t } from '@nextcloud/l10n'
import { generateUrl } from '@nextcloud/router'
import { showUserModal } from './components/UserModal'
import type { UserModalData } from './components/UserModal'

// 1. Register WebDAV properties so Nextcloud PROPFIND requests them automatically
registerDavProperty('nc:creation_time', { nc: 'http://nextcloud.org/ns' })
registerDavProperty('nc:upload_time', { nc: 'http://nextcloud.org/ns' })
registerDavProperty('nc:files-grid-uploaded-by', { nc: 'http://nextcloud.org/ns' })
registerDavProperty('nc:files-grid-modified-by', { nc: 'http://nextcloud.org/ns' })

export interface UserInfo extends UserModalData {
	uid: string
	displayName: string
	email?: string
}

function parseUserInfo(raw: unknown): UserInfo | null {
	if (!raw) {
		return null
	}
	if (typeof raw === 'object' && raw !== null && 'uid' in raw) {
		return raw as UserInfo
	}
	if (typeof raw === 'string') {
		try {
			const parsed = JSON.parse(raw)
			if (parsed && typeof parsed === 'object' && parsed.uid) {
				return parsed as UserInfo
			}
		} catch {
			// Plain string or user id
			return { uid: raw, displayName: raw }
		}
	}
	return null
}

export function getUploaderInfo(node: any): UserInfo | null {
	const raw = node.attributes?.['files-grid-uploaded-by']
	const parsed = parseUserInfo(raw)
	if (parsed) {
		return parsed
	}
	// Fallback to node owner
	const ownerUid = node.owner || node.attributes?.['owner-id']
	const ownerDisplay = node.attributes?.['owner-display-name'] || ownerUid
	if (ownerUid) {
		return { uid: ownerUid, displayName: ownerDisplay }
	}
	return null
}

export function getModifierInfo(node: any): UserInfo | null {
	const raw = node.attributes?.['files-grid-modified-by']
	const parsed = parseUserInfo(raw)
	if (parsed) {
		return parsed
	}
	// Fallback to uploader info
	return getUploaderInfo(node)
}

export function parseTimestamp(raw: unknown): number {
	if (typeof raw === 'number' && !isNaN(raw)) {
		return raw > 0 && raw < 1e11 ? raw * 1000 : raw
	}
	if (typeof raw === 'string') {
		const num = Number(raw)
		if (!isNaN(num) && num > 0) {
			return num < 1e11 ? num * 1000 : num
		}
		const parsed = Date.parse(raw)
		if (!isNaN(parsed)) {
			return parsed
		}
	}
	if (raw instanceof Date) {
		return raw.getTime()
	}
	return 0
}

export function getCreationTimestamp(node: any): number {
	const raw = node.attributes?.creation_time
		?? node.attributes?.['creation_time']
		?? node.crtime
	const ts = parseTimestamp(raw)
	if (ts > 0) {
		return ts
	}
	return getUploadTimestamp(node)
}

export function getUploadTimestamp(node: any): number {
	const raw = node.attributes?.upload_time ?? node.attributes?.['upload_time']
	const ts = parseTimestamp(raw)
	if (ts > 0) {
		return ts
	}
	return getMtimeTimestamp(node)
}

export function getMtimeTimestamp(node: any): number {
	const ts = parseTimestamp(node.mtime)
	return ts > 0 ? ts : 0
}

const dateTimeFormatter = new Intl.DateTimeFormat(undefined, {
	year: 'numeric',
	month: '2-digit',
	day: '2-digit',
	hour: '2-digit',
	minute: '2-digit',
	second: '2-digit',
})

export function renderDateCell(timestamp: number, columnId: string): HTMLElement {
	const span = document.createElement('span')
	span.className = `files-detailed-grid-date-cell files-detailed-grid-col-${columnId}`

	if (!timestamp || timestamp <= 0) {
		span.className += ' files-detailed-grid-empty'
		span.textContent = '—'
		return span
	}

	const date = new Date(timestamp)
	try {
		span.textContent = formatRelativeTime(date)
	} catch {
		span.textContent = dateTimeFormatter.format(date)
	}
	span.title = dateTimeFormatter.format(date)
	return span
}

export function renderUserCell(user: UserInfo | null): HTMLElement {
	if (!user || !user.uid) {
		const emptySpan = document.createElement('span')
		emptySpan.className = 'files-detailed-grid-empty'
		emptySpan.textContent = '—'
		return emptySpan
	}

	const button = document.createElement('button')
	button.type = 'button'
	button.className = 'files-detailed-grid-user-cell'
	button.title = `${user.displayName} (${user.uid})`
	button.setAttribute('aria-label', `${user.displayName} (${user.uid})`)

	const avatar = document.createElement('img')
	avatar.className = 'files-detailed-grid-avatar'
	avatar.src = generateUrl('/avatar/{user}/{size}', { user: user.uid, size: 32 })
	avatar.alt = user.displayName
	avatar.loading = 'lazy'
	avatar.onerror = () => {
		avatar.style.display = 'none'
	}

	const nameSpan = document.createElement('span')
	nameSpan.className = 'files-detailed-grid-username'
	nameSpan.textContent = user.displayName

	button.appendChild(avatar)
	button.appendChild(nameSpan)

	button.onclick = (e) => {
		e.preventDefault()
		e.stopPropagation()
		showUserModal(user)
	}

	return button
}

// 2. Column Visibility Management (Persistence)
export interface DateColumnsVisibility {
	creation: boolean
	upload: boolean
	mtime: boolean
}

const STORAGE_KEY = 'files_detailed_grid_hzs_visible_date_cols'

export function loadColumnsVisibility(): DateColumnsVisibility {
	try {
		const raw = localStorage.getItem(STORAGE_KEY)
		if (raw) {
			const parsed = JSON.parse(raw)
			return {
				creation: parsed.creation !== false,
				upload: parsed.upload !== false,
				mtime: parsed.mtime !== false,
			}
		}
	} catch {
		// Ignore storage errors
	}
	return { creation: true, upload: true, mtime: true }
}

export function saveColumnsVisibility(state: DateColumnsVisibility): void {
	try {
		localStorage.setItem(STORAGE_KEY, JSON.stringify(state))
	} catch {
		// Ignore storage errors
	}
	applyColumnsVisibility(state)
}

export function applyColumnsVisibility(state: DateColumnsVisibility): void {
	let styleEl = document.getElementById('files-detailed-grid-col-visibility-style') as HTMLStyleElement | null
	if (!styleEl) {
		styleEl = document.createElement('style')
		styleEl.id = 'files-detailed-grid-col-visibility-style'
		document.head.appendChild(styleEl)
	}

	const rules: string[] = []
	if (!state.creation) {
		rules.push(`
			.files-detailed-grid-col-hzs_creation_time,
			th:has(.files-detailed-grid-col-hzs_creation_time),
			th[data-column-id="hzs_creation_time"],
			th[data-cy-files-list-header-mode="hzs_creation_time"],
			td:has(.files-detailed-grid-col-hzs_creation_time) {
				display: none !important;
			}
		`)
	}
	if (!state.upload) {
		rules.push(`
			.files-detailed-grid-col-hzs_upload_time,
			th:has(.files-detailed-grid-col-hzs_upload_time),
			th[data-column-id="hzs_upload_time"],
			th[data-cy-files-list-header-mode="hzs_upload_time"],
			td:has(.files-detailed-grid-col-hzs_upload_time) {
				display: none !important;
			}
		`)
	}
	if (!state.mtime) {
		rules.push(`
			.files-detailed-grid-col-hzs_mtime,
			th:has(.files-detailed-grid-col-hzs_mtime),
			th[data-column-id="hzs_mtime"],
			th[data-cy-files-list-header-mode="hzs_mtime"],
			td:has(.files-detailed-grid-col-hzs_mtime) {
				display: none !important;
			}
		`)
	}

	styleEl.textContent = rules.join('\n')
	updateViewsColumns(state)
}

// 3. Define Columns
export function createDetailedColumns(): Column[] {
	return [
		new Column({
			id: 'hzs_creation_time',
			title: t('files_detailed_grid_hzs', 'Creation date'),
			render: (node: any) => renderDateCell(getCreationTimestamp(node), 'hzs_creation_time'),
			sort: (a: any, b: any) => getCreationTimestamp(a) - getCreationTimestamp(b),
		}),
		new Column({
			id: 'hzs_upload_time',
			title: t('files_detailed_grid_hzs', 'Upload date'),
			render: (node: any) => renderDateCell(getUploadTimestamp(node), 'hzs_upload_time'),
			sort: (a: any, b: any) => getUploadTimestamp(a) - getUploadTimestamp(b),
		}),
		new Column({
			id: 'hzs_mtime',
			title: t('files_detailed_grid_hzs', 'Last modified date'),
			render: (node: any) => renderDateCell(getMtimeTimestamp(node), 'hzs_mtime'),
			sort: (a: any, b: any) => getMtimeTimestamp(a) - getMtimeTimestamp(b),
		}),
		new Column({
			id: 'hzs_uploaded_by',
			title: t('files_detailed_grid_hzs', 'Uploaded by'),
			render: (node: any) => renderUserCell(getUploaderInfo(node)),
			sort: (a: any, b: any) => {
				const nameA = getUploaderInfo(a)?.displayName || ''
				const nameB = getUploaderInfo(b)?.displayName || ''
				return nameA.localeCompare(nameB)
			},
		}),
		new Column({
			id: 'hzs_modified_by',
			title: t('files_detailed_grid_hzs', 'Modified by'),
			render: (node: any) => renderUserCell(getModifierInfo(node)),
			sort: (a: any, b: any) => {
				const nameA = getModifierInfo(a)?.displayName || ''
				const nameB = getModifierInfo(b)?.displayName || ''
				return nameA.localeCompare(nameB)
			},
		}),
	]
}

// 4. Custom Filters (Date Columns Visibility Filter, Date Range Filter, User Filter)
export class DetailedColumnsFilter extends FileListFilter implements IFileListFilterWithUi {
	readonly displayName = t('files_detailed_grid_hzs', 'Date columns')
	readonly iconSvgInline = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M3 3h18v18H3V3zm2 2v14h4V5H5zm6 0v14h4V5h-4zm6 0v14h2V5h-2z"/></svg>'
	readonly tagName = 'files-detailed-grid-columns-filter'

	constructor() {
		super('files_detailed_grid_hzs:columns', 45)
	}

	filter(nodes: any[]): any[] {
		return nodes
	}
}

export class DetailedDateFilter extends FileListFilter implements IFileListFilterWithUi {
	readonly displayName = t('files_detailed_grid_hzs', 'Filter by date')
	readonly iconSvgInline = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V10h14v10zm0-12H5V6h14v2z"/></svg>'
	readonly tagName = 'files-detailed-grid-date-filter'

	private minTimestamp: number | null = null
	private maxTimestamp: number | null = null

	constructor() {
		super('files_detailed_grid_hzs:date', 50)
	}

	setDaysPreset(label: string, days: number): void {
		const now = Date.now()
		const msPerDay = 24 * 60 * 60 * 1000
		if (days === 1) {
			const today = new Date()
			today.setHours(0, 0, 0, 0)
			this.minTimestamp = today.getTime()
			this.maxTimestamp = null
		} else if (days === 2) {
			const yesterdayStart = new Date()
			yesterdayStart.setDate(yesterdayStart.getDate() - 1)
			yesterdayStart.setHours(0, 0, 0, 0)
			const yesterdayEnd = new Date()
			yesterdayEnd.setDate(yesterdayEnd.getDate() - 1)
			yesterdayEnd.setHours(23, 59, 59, 999)
			this.minTimestamp = yesterdayStart.getTime()
			this.maxTimestamp = yesterdayEnd.getTime()
		} else {
			this.minTimestamp = now - days * msPerDay
			this.maxTimestamp = null
		}

		this.updateChips([
			{
				text: label,
				onclick: () => this.reset(),
			},
		])
		this.filterUpdated()
	}

	filter(nodes: any[]): any[] {
		if (this.minTimestamp === null && this.maxTimestamp === null) {
			return nodes
		}
		return nodes.filter((node) => {
			const mtime = getMtimeTimestamp(node)
			const upload = getUploadTimestamp(node)
			const create = getCreationTimestamp(node)
			const maxTs = Math.max(mtime, upload, create)
			if (maxTs <= 0) {
				return false
			}
			if (this.minTimestamp !== null && maxTs < this.minTimestamp) {
				return false
			}
			if (this.maxTimestamp !== null && maxTs > this.maxTimestamp) {
				return false
			}
			return true
		})
	}

	reset(): void {
		this.minTimestamp = null
		this.maxTimestamp = null
		this.updateChips([])
		this.filterUpdated()
	}
}

export class DetailedUserFilter extends FileListFilter implements IFileListFilterWithUi {
	readonly displayName = t('files_detailed_grid_hzs', 'Filter by user')
	readonly iconSvgInline = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>'
	readonly tagName = 'files-detailed-grid-user-filter'

	private selectedUid: string | null = null
	private lastKnownNodes: any[] = []

	constructor() {
		super('files_detailed_grid_hzs:user', 55)
	}

	setSelectedUser(user: UserInfo): void {
		this.selectedUid = user.uid
		this.updateChips([
			{
				text: user.displayName,
				user: user.uid,
				onclick: () => this.reset(),
			},
		])
		this.filterUpdated()
	}

	getAvailableUsers(): UserInfo[] {
		const map = new Map<string, UserInfo>()
		for (const node of this.lastKnownNodes) {
			const uploader = getUploaderInfo(node)
			if (uploader?.uid) {
				map.set(uploader.uid, uploader)
			}
			const modifier = getModifierInfo(node)
			if (modifier?.uid) {
				map.set(modifier.uid, modifier)
			}
		}
		return Array.from(map.values())
	}

	filter(nodes: any[]): any[] {
		this.lastKnownNodes = nodes
		if (!this.selectedUid) {
			return nodes
		}
		return nodes.filter((node) => {
			const uploader = getUploaderInfo(node)
			const modifier = getModifierInfo(node)
			return uploader?.uid === this.selectedUid || modifier?.uid === this.selectedUid
		})
	}

	reset(): void {
		this.selectedUid = null
		this.updateChips([])
		this.filterUpdated()
	}
}

// 5. Custom Web Components for Filter Menus
class ColumnsFilterComponent extends HTMLElement {
	private _filter: DetailedColumnsFilter | null = null

	set filter(f: DetailedColumnsFilter) {
		this._filter = f
		this.render()
	}

	get filter(): DetailedColumnsFilter | null {
		return this._filter
	}

	connectedCallback() {
		this.render()
	}

	private render() {
		this.innerHTML = ''
		const container = document.createElement('div')
		container.className = 'files-detailed-grid-filter-menu files-detailed-grid-columns-menu'

		const title = document.createElement('div')
		title.className = 'files-detailed-grid-filter-title'
		title.textContent = t('files_detailed_grid_hzs', 'Date columns')
		container.appendChild(title)

		const currentVisibility = loadColumnsVisibility()

		const items: { key: keyof DateColumnsVisibility; label: string }[] = [
			{ key: 'creation', label: t('files_detailed_grid_hzs', 'Creation date') },
			{ key: 'upload', label: t('files_detailed_grid_hzs', 'Upload date') },
			{ key: 'mtime', label: t('files_detailed_grid_hzs', 'Last modified date') },
		]

		for (const item of items) {
			const label = document.createElement('label')
			label.className = 'files-detailed-grid-checkbox-label'

			const checkbox = document.createElement('input')
			checkbox.type = 'checkbox'
			checkbox.checked = currentVisibility[item.key]
			checkbox.onchange = () => {
				const updated = loadColumnsVisibility()
				updated[item.key] = checkbox.checked
				saveColumnsVisibility(updated)
			}

			const text = document.createElement('span')
			text.textContent = item.label

			label.appendChild(checkbox)
			label.appendChild(text)
			container.appendChild(label)
		}

		this.appendChild(container)
	}
}

class DateFilterComponent extends HTMLElement {
	private _filter: DetailedDateFilter | null = null

	set filter(f: DetailedDateFilter) {
		this._filter = f
		this.render()
	}

	get filter(): DetailedDateFilter | null {
		return this._filter
	}

	connectedCallback() {
		this.render()
	}

	private render() {
		this.innerHTML = ''
		const container = document.createElement('div')
		container.className = 'files-detailed-grid-filter-menu'

		const presets = [
			{ label: t('files_detailed_grid_hzs', 'Today'), days: 1 },
			{ label: t('files_detailed_grid_hzs', 'Yesterday'), days: 2 },
			{ label: t('files_detailed_grid_hzs', 'Last 7 days'), days: 7 },
			{ label: t('files_detailed_grid_hzs', 'Last 30 days'), days: 30 },
		]

		for (const preset of presets) {
			const btn = document.createElement('button')
			btn.type = 'button'
			btn.className = 'files-detailed-grid-filter-option'
			btn.textContent = preset.label
			btn.onclick = (e) => {
				e.stopPropagation()
				if (this._filter) {
					this._filter.setDaysPreset(preset.label, preset.days)
				}
			}
			container.appendChild(btn)
		}

		const resetBtn = document.createElement('button')
		resetBtn.type = 'button'
		resetBtn.className = 'files-detailed-grid-filter-option files-detailed-grid-filter-reset'
		resetBtn.textContent = t('files_detailed_grid_hzs', 'Reset')
		resetBtn.onclick = (e) => {
			e.stopPropagation()
			if (this._filter) {
				this._filter.reset()
			}
		}
		container.appendChild(resetBtn)

		this.appendChild(container)
	}
}

class UserFilterComponent extends HTMLElement {
	private _filter: DetailedUserFilter | null = null

	set filter(f: DetailedUserFilter) {
		this._filter = f
		this.render()
	}

	get filter(): DetailedUserFilter | null {
		return this._filter
	}

	connectedCallback() {
		this.render()
	}

	private render() {
		this.innerHTML = ''
		const container = document.createElement('div')
		container.className = 'files-detailed-grid-filter-menu'

		const input = document.createElement('input')
		input.type = 'text'
		input.className = 'files-detailed-grid-filter-input'
		input.placeholder = t('files_detailed_grid_hzs', 'Search user...')

		const list = document.createElement('div')
		list.className = 'files-detailed-grid-filter-user-list'

		const renderOptions = (query: string) => {
			list.innerHTML = ''
			const users = this._filter ? this._filter.getAvailableUsers() : []
			const q = query.trim().toLowerCase()
			const filtered = q
				? users.filter(
					(u) =>
						u.displayName.toLowerCase().includes(q)
						|| u.uid.toLowerCase().includes(q),
				)
				: users

			if (filtered.length === 0) {
				const empty = document.createElement('div')
				empty.className = 'files-detailed-grid-filter-empty'
				empty.textContent = t('files_detailed_grid_hzs', 'No users found')
				list.appendChild(empty)
				return
			}

			for (const user of filtered) {
				const btn = document.createElement('button')
				btn.type = 'button'
				btn.className = 'files-detailed-grid-filter-user-option'

				const avatar = document.createElement('img')
				avatar.className = 'files-detailed-grid-avatar'
				avatar.src = generateUrl('/avatar/{user}/{size}', { user: user.uid, size: 24 })
				avatar.alt = user.displayName

				const name = document.createElement('span')
				name.textContent = user.displayName

				btn.appendChild(avatar)
				btn.appendChild(name)

				btn.onclick = (e) => {
					e.stopPropagation()
					if (this._filter) {
						this._filter.setSelectedUser(user)
					}
				}
				list.appendChild(btn)
			}
		}

		input.oninput = () => {
			renderOptions(input.value)
		}

		container.appendChild(input)
		container.appendChild(list)
		this.appendChild(container)
		renderOptions('')
	}
}

if (!customElements.get('files-detailed-grid-columns-filter')) {
	customElements.define('files-detailed-grid-columns-filter', ColumnsFilterComponent)
}
if (!customElements.get('files-detailed-grid-date-filter')) {
	customElements.define('files-detailed-grid-date-filter', DateFilterComponent)
}
if (!customElements.get('files-detailed-grid-user-filter')) {
	customElements.define('files-detailed-grid-user-filter', UserFilterComponent)
}

// 6. Filter Management: Unregister Standard Modified/People filters and register custom ones
function initFilters() {
	try {
		// Unregister standard Modified and People filters
		unregisterFileListFilter('files:modified')
		unregisterFileListFilter('files_sharing:account')

		for (const filter of getFileListFilters()) {
			if (
				filter.id === 'files:modified'
				|| filter.id === 'files_sharing:account'
				|| filter.id.endsWith(':modified')
			) {
				unregisterFileListFilter(filter.id)
			}
		}

		// Intercept late registrations of standard filters
		try {
			getFilesRegistry().addEventListener('register:listFilter', (event: any) => {
				const registered = event.detail
				if (
					registered
					&& (registered.id === 'files:modified'
						|| registered.id === 'files_sharing:account')
				) {
					setTimeout(() => unregisterFileListFilter(registered.id), 0)
				}
			})
		} catch {
			// Registry listener optional
		}

		// Register custom columns visibility filter, date filter, and user filter
		registerFileListFilter(new DetailedColumnsFilter())
		registerFileListFilter(new DetailedDateFilter())
		registerFileListFilter(new DetailedUserFilter())
	} catch (error) {
		console.error('[files_detailed_grid_hzs] Failed to initialize custom filters:', error)
	}
}

// 7. Register Columns in Views
function updateViewsColumns(visibility: DateColumnsVisibility) {
	try {
		const nav = getNavigation()
		const allDetailedColumns = createDetailedColumns()

		const filteredColumns = allDetailedColumns.filter((col) => {
			if (col.id === 'hzs_creation_time' && !visibility.creation) return false
			if (col.id === 'hzs_upload_time' && !visibility.upload) return false
			if (col.id === 'hzs_mtime' && !visibility.mtime) return false
			return true
		})

		const attach = (view: any) => {
			if (!view || !view._view) return
			if (!view._view.columns) view._view.columns = []

			// Remove standard mtime column
			view._view.columns = view._view.columns.filter((c: any) => c.id !== 'mtime')

			// Remove existing detailed columns
			const allIds = new Set(allDetailedColumns.map((c) => c.id))
			view._view.columns = view._view.columns.filter((c: any) => !allIds.has(c.id))

			// Add only the currently enabled detailed columns
			for (const col of filteredColumns) {
				view._view.columns.push(col)
			}
		}

		for (const view of nav.views) {
			attach(view)
		}
	} catch (e) {
		console.error('[files_detailed_grid_hzs] Failed to update view columns:', e)
	}
}

function registerColumnsInViews() {
	try {
		const nav = getNavigation()
		const visibility = loadColumnsVisibility()

		updateViewsColumns(visibility)

		nav.addEventListener('update', () => {
			updateViewsColumns(loadColumnsVisibility())
		})
	} catch (error) {
		console.error('[files_detailed_grid_hzs] Failed to register columns in views:', error)
	}
}

// 8. Initialization
function initialize() {
	initFilters()
	registerColumnsInViews()
	applyColumnsVisibility(loadColumnsVisibility())
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', initialize)
} else {
	initialize()
}
