import { App } from 'vue';
import { vCan } from './can';
import { vRole } from './role';

export { vCan, vRole };

export function registerDirectives(app: App): void {
    app.directive('can', vCan);
    app.directive('role', vRole);
}

export default registerDirectives;
