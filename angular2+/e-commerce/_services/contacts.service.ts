// Angular
import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
// RxJS
import { Observable, BehaviorSubject } from 'rxjs';
// CRUD
import { HttpUtilsService, QueryParamsModel, QueryResultsModel } from '../../_base/crud';
// Models
import { ContactModel } from '../_models/contact.model';

const API_CONTACTS_URL = 'http://admin/contacts';
// Real REST API
@Injectable()
export class ContactsService {

	lastFilter$: BehaviorSubject<QueryParamsModel> = new BehaviorSubject(new QueryParamsModel({}, 'asc', '', 0, 10));

	constructor(private http: HttpClient, private httpUtils: HttpUtilsService) { }

	// CREATE =>  POST: add a new contact to the server
	createContact(contact): Observable<ContactModel> {
		const httpHeaders = this.httpUtils.getHTTPHeaders();
		return this.http.post<ContactModel>(API_CONTACTS_URL, contact, { headers: httpHeaders });
	}

	// READ
	getAllContacts(): Observable<ContactModel[]> {
		return this.http.get<ContactModel[]>(API_CONTACTS_URL);
	}

	getContactById(contactId: number): Observable<ContactModel> {
		return this.http.get<ContactModel>(API_CONTACTS_URL + `/${contactId}`);
	}

	// Server should return filtered/sorted result
	findContacts(queryParams: QueryParamsModel): Observable<QueryResultsModel> {
			// Note: Add headers if needed (tokens/bearer)
			const httpHeaders = this.httpUtils.getHTTPHeaders();
			const httpParams = this.httpUtils.getFindHTTPParams(queryParams);

			const url = API_CONTACTS_URL + '/find';
			return this.http.get<QueryResultsModel>(url, {
				headers: httpHeaders,
				params:  httpParams
			});
	}

	// UPDATE => PUT: update the contact on the server
	updateContact(contact: ContactModel): Observable<any> {
		// Note: Add headers if needed (tokens/bearer)
		const httpHeaders = this.httpUtils.getHTTPHeaders();
		return this.http.put(API_CONTACTS_URL, contact, { headers: httpHeaders });
	}

	// UPDATE Status
	// Comment this when you start work with real server
	// This code imitates server calls
	updateStatusForContact(contacts: ContactModel[], status: number): Observable<any> {
		const httpHeaders = this.httpUtils.getHTTPHeaders();
		const body = {
			contactsForUpdate: contacts,
			newStatus: status
		};
		const url = API_CONTACTS_URL + '/updateStatus';
		return this.http.put(url, body, { headers: httpHeaders });
	}

	// DELETE => delete the contact from the server
	deleteContact(contactId: number): Observable<ContactModel> {
		const url = `${API_CONTACTS_URL}/${contactId}`;
		return this.http.delete<ContactModel>(url);
	}

	deleteContacts(ids: number[] = []): Observable<any> {
		const url = API_CONTACTS_URL + '/delete';
		const httpHeaders = this.httpUtils.getHTTPHeaders();
		const body = { prdocutIdsForDelete: ids };
		return this.http.put<QueryResultsModel>(url, body, { headers: httpHeaders} );
	}
}
