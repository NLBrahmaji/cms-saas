export type AccessibleAccount = {
  id: number;
  name: string;
  status: string;
  role: string | null;
};

export type AccountsListResponse = {
  data: AccessibleAccount[];
};

export type AccountDetail = {
  id: number;
  name: string;
  status: string;
};

export type AccountShowResponse = {
  data: AccountDetail;
};
