import { usePage } from "@inertiajs/vue3";
import type { PageProps } from "@/types";
export function usePermissions() {
    const page = usePage<PageProps>();
    return {
        can: (permission: string) =>
            page.props.permissions?.includes(permission) ?? false,
    };
}
