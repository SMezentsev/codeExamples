
// NGRX
import { createFeatureSelector } from '@ngrx/store';
import { EntityState, EntityAdapter, createEntityAdapter, Update } from '@ngrx/entity';
// Actions
import { ContactsActions, ContactsActionTypes } from '../_actions/contacts.actions';
// CRUD
import { QueryParamsModel } from '../../_base/crud';
// Models
import { ContactsModel } from '../_models/contacts.model';

export interface ContactsState extends EntityState<ContactsModel> {
	listLoading: boolean;
	actionsloading: boolean;
	totalCount: number;
	lastQuery: QueryParamsModel;
	lastCreatedContactId: number;
	showInitWaitingMessage: boolean;
}

export const adapter: EntityAdapter<ContactsModel> = createEntityAdapter<ContactsModel>();

export const initialContactsState: ContactsState = adapter.getInitialState({
	listLoading: false,
	actionsloading: false,
	totalCount: 0,
	lastQuery:  new QueryParamsModel({}),
	lastCreatedContactId: undefined,
	showInitWaitingMessage: true
});

export function contactsReducer(state = initialContactsState, action: ContactsActions): ContactsState {
	switch  (action.type) {
		case ContactsActionTypes.ContactsPageToggleLoading: return {
			...state, listLoading: action.payload.isLoading, lastCreatedContactId: undefined
		};
		case ContactsActionTypes.ContactsActionToggleLoading: return {
			...state, actionsloading: action.payload.isLoading
		};
		case ContactsActionTypes.ContactsOnServerCreated: return {
			...state
		};
		case ContactsActionTypes.ContactsCreated: return adapter.addOne(action.payload.contact, {
			...state, lastCreatedContactId: action.payload.contact.id
		});
		case ContactsActionTypes.ContactsUpdated: return adapter.updateOne(action.payload.partialContacts, state);
		case ContactsActionTypes.ContactsStatusUpdated: {
			const _partialContacts: Update<ContactsModel>[] = [];
			for (let i = 0; i < action.payload.contacts.length; i++) {
				_partialContacts.push({
					id: action.payload.contacts[i].id,
					changes: {
						status: action.payload.status
					}
				});
			}
			return adapter.updateMany(_partialContacts, state);
		}
		// case ContactsActionTypes.OneContactDeleted: return adapter.removeOne(action.payload.id, state);
		case ContactsActionTypes.ManyContactsDeleted: return adapter.removeMany(action.payload.ids, state);
		case ContactsActionTypes.ContactsPageCancelled: return {
			...state, listLoading: false, lastQuery: new QueryParamsModel({})
		};
		case ContactsActionTypes.ContactsPageLoaded:
			return adapter.addMany(action.payload.contacts, {
				...initialContactsState,
				totalCount: action.payload.totalCount,
				listLoading: false,
				lastQuery: action.payload.page,
				showInitWaitingMessage: false
			});
		default: return state;
	}
}

export const getContactState = createFeatureSelector<ContactsModel>('contacts');

export const {
	selectAll,
	selectEntities,
	selectIds,
	selectTotal
} = adapter.getSelectors();
