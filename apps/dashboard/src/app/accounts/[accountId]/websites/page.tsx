import { AccountWebsitesPlaceholder } from "@/features/accounts/components/account-websites-placeholder";

type AccountWebsitesPageProps = {
  params: Promise<{
    accountId: string;
  }>;
};

export default async function AccountWebsitesPage({
  params,
}: AccountWebsitesPageProps) {
  const { accountId } = await params;

  return <AccountWebsitesPlaceholder accountIdParam={accountId} />;
}
