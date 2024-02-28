import { BaseModel } from '../../_base/crud';
import { ProductSpecificationModel } from './product-specification.model';
import { ProductRemarkModel } from './product-remark.model';

export class ProductModel extends BaseModel {
	id: number;
    name: string;
    category_id: number;
    group_id: number;
    stock_id: number;
    city: string;
    manufacturer_id: number;
    packaging_type_id: number;
    weight: number;
    color_id: string;
    code: number;
    main: number;
	show: number;
	status: number;




	_specs: ProductSpecificationModel[];
	_remarks: ProductRemarkModel[];

	clear() {
		this.name = '';
		this.category_id = undefined;
		this.group_id = undefined;
		this.stock_id = undefined;
		this.city = undefined;
		this.manufacturer_id = undefined;
		this.packaging_type_id = undefined;
		this.weight = undefined;
		this.color_id = '';
		this.code = undefined;
		this.main = undefined;
		this.show = undefined;
		this.status = 1;
	}
}
